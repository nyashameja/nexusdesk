<?php

declare(strict_types=1);

namespace App\Controllers\Web\Desk;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\KbRepositoryInterface;
use App\Services\Audit\AuditService;
use App\Services\Auth\AuthContext;
use App\Support\Str;
use App\Support\Validation\Validator;

/**
 * Knowledge-base management for staff (requires kb.manage). Create/edit/publish
 * articles and add categories.
 */
final class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly KbRepositoryInterface $kb,
        private readonly AuditService $audit,
    ) {
    }

    private function guard(): void
    {
        if (!AuthContext::can('kb.manage')) {
            throw HttpException::forbidden('You cannot manage the knowledge base.');
        }
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = $request->query('q') !== null ? (string) $request->query('q') : null;
        return $this->view('desk.kb_index', [
            'title'    => 'Knowledge base',
            'active'   => 'kb',
            'articles' => $this->kb->allArticles($page, 20, $search),
            'total'    => $this->kb->countArticles($search),
            'page'     => $page,
            'perPage'  => 20,
            'search'   => $search,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('desk.kb_edit', [
            'title'      => 'New article',
            'active'     => 'kb',
            'article'    => null,
            'categories' => $this->kb->categories(publicOnly: false),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->guard();
        $validator = Validator::make(
            $request->only(['category_id', 'title', 'body_html', 'status']),
            [
                'category_id' => 'required|integer',
                'title'       => 'required|max:255',
                'body_html'   => 'required|min:10',
                'status'      => 'required|in:draft,published,archived',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/desk/kb/new'))->withErrors($validator->errors())->withInput($request->all());
        }

        $title = (string) $request->input('title');
        $status = (string) $request->input('status');
        $id = $this->kb->createArticle([
            'category_id' => (int) $request->input('category_id'),
            'author_id'   => AuthContext::id(),
            'title'       => $title,
            'slug'        => Str::slug($title) . '-' . substr((string) time(), -5),
            'excerpt'     => Str::excerpt((string) $request->input('body_html')),
            'body_html'   => (string) $request->input('body_html'),
            'is_public'   => (string) $request->input('is_public', '1') === '1' ? 1 : 0,
            'status'      => $status,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
        $this->audit->log('kb.article_created', 'KbArticle', $id);

        return (new RedirectResponse('/desk/kb'))->with('status', 'Article created.');
    }

    public function edit(Request $request, int $id): Response
    {
        $article = $this->kb->articleById($id);
        if ($article === null) {
            throw HttpException::notFound('Article not found.');
        }
        return $this->view('desk.kb_edit', [
            'title'      => 'Edit article',
            'active'     => 'kb',
            'article'    => $article,
            'categories' => $this->kb->categories(publicOnly: false),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->guard();
        $article = $this->kb->articleById($id);
        if ($article === null) {
            throw HttpException::notFound('Article not found.');
        }
        $validator = Validator::make(
            $request->only(['category_id', 'title', 'body_html', 'status']),
            [
                'category_id' => 'required|integer',
                'title'       => 'required|max:255',
                'body_html'   => 'required|min:10',
                'status'      => 'required|in:draft,published,archived',
            ]
        );
        if ($validator->fails()) {
            return (new RedirectResponse('/desk/kb/' . $id . '/edit'))->withErrors($validator->errors());
        }

        $status = (string) $request->input('status');
        $update = [
            'category_id' => (int) $request->input('category_id'),
            'title'       => (string) $request->input('title'),
            'excerpt'     => Str::excerpt((string) $request->input('body_html')),
            'body_html'   => (string) $request->input('body_html'),
            'is_public'   => (string) $request->input('is_public', '1') === '1' ? 1 : 0,
            'status'      => $status,
        ];
        if ($status === 'published' && $article['published_at'] === null) {
            $update['published_at'] = date('Y-m-d H:i:s');
        }
        $this->kb->updateArticle($id, $update);
        $this->audit->log('kb.article_updated', 'KbArticle', $id);

        return (new RedirectResponse('/desk/kb'))->with('status', 'Article updated.');
    }

    public function delete(Request $request, int $id): Response
    {
        $this->guard();
        $this->kb->deleteArticle($id);
        $this->audit->log('kb.article_deleted', 'KbArticle', $id);
        return (new RedirectResponse('/desk/kb'))->with('status', 'Article deleted.');
    }
}
