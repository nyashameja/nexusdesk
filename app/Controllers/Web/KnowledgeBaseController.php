<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\KbRepositoryInterface;
use App\Services\Auth\AuthContext;

/**
 * Public knowledge base: browse categories, read articles, search, and leave
 * "was this helpful?" feedback. Staff/customers see the same base; internal
 * (non-public) articles are only shown to signed-in staff.
 */
final class KnowledgeBaseController extends Controller
{
    public function __construct(private readonly KbRepositoryInterface $kb)
    {
    }

    private function staffViewer(): bool
    {
        return AuthContext::user()?->isStaff() ?? false;
    }

    public function index(Request $request): Response
    {
        $publicOnly = !$this->staffViewer();
        return $this->view('kb.index', [
            'title'      => 'Knowledge base',
            'active'     => 'kb',
            'categories' => $this->kb->categories($publicOnly),
            'popular'    => $this->kb->popular(5, $publicOnly),
        ], $this->layout());
    }

    public function category(Request $request, string $slug): Response
    {
        $category = $this->kb->category($slug);
        if ($category === null) {
            throw HttpException::notFound('Category not found.');
        }
        $publicOnly = !$this->staffViewer();
        return $this->view('kb.category', [
            'title'    => $category['name'],
            'active'   => 'kb',
            'category' => $category,
            'articles' => $this->kb->articlesInCategory((int) $category['id'], $publicOnly),
        ], $this->layout());
    }

    public function article(Request $request, string $slug): Response
    {
        $article = $this->kb->article($slug, publishedOnly: !$this->staffViewer());
        if ($article === null) {
            throw HttpException::notFound('Article not found.');
        }
        // Non-public article requested by a non-staff viewer → hide.
        if (!$article['is_public'] && !$this->staffViewer()) {
            throw HttpException::notFound('Article not found.');
        }

        $this->kb->incrementViews((int) $article['id']);

        return $this->view('kb.article', [
            'title'   => $article['title'],
            'active'  => 'kb',
            'article' => $article,
            'related' => $this->kb->related((int) $article['id']),
        ], $this->layout());
    }

    public function search(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $results = $query !== '' ? $this->kb->search($query, publicOnly: !$this->staffViewer()) : [];

        return $this->view('kb.search', [
            'title'   => 'Search: ' . $query,
            'active'  => 'kb',
            'query'   => $query,
            'results' => $results,
        ], $this->layout());
    }

    public function feedback(Request $request, int $id): Response
    {
        $article = $this->kb->articleById($id);
        if ($article !== null) {
            $this->kb->recordFeedback(
                $id,
                (string) $request->input('helpful') === '1',
                AuthContext::id(),
                $request->input('comment') !== '' ? (string) $request->input('comment') : null,
                $request->ip()
            );
        }
        return (new RedirectResponse('/kb/a/' . ($article['slug'] ?? '')))
            ->with('status', 'Thanks for your feedback!');
    }

    /** Signed-in users get the app shell; guests get the public layout. */
    private function layout(): string
    {
        return AuthContext::check() ? 'layouts/app' : 'layouts/guest';
    }
}
