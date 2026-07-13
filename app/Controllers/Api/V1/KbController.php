<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Repositories\Contracts\KbRepositoryInterface;

final class KbController
{
    public function __construct(private readonly KbRepositoryInterface $kb)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if ($query !== '') {
            return JsonResponse::ok($this->kb->search($query));
        }
        return JsonResponse::ok($this->kb->categories());
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $article = $this->kb->article($slug);
        if ($article === null) {
            return JsonResponse::error('not_found', 'Article not found.', 404);
        }
        $this->kb->incrementViews((int) $article['id']);
        return JsonResponse::ok([
            'id'       => (int) $article['id'],
            'title'    => $article['title'],
            'slug'     => $article['slug'],
            'category' => $article['category_name'],
            'body'     => $article['body_html'],
            'helpful'  => (int) $article['helpful_count'],
        ]);
    }
}
