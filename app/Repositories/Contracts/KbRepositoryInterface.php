<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface KbRepositoryInterface
{
    /** @return array<int,array<string,mixed>> */
    public function categories(bool $publicOnly = true): array;
    public function category(string $slug): ?array;
    public function categoryById(int $id): ?array;

    /** @return array<int,array<string,mixed>> Published articles in a category. */
    public function articlesInCategory(int $categoryId, bool $publicOnly = true): array;
    public function article(string $slug, bool $publishedOnly = true): ?array;
    public function articleById(int $id): ?array;
    public function incrementViews(int $id): void;

    /** @return array<int,array<string,mixed>> */
    public function search(string $query, bool $publicOnly = true, int $limit = 20): array;
    /** @return array<int,array<string,mixed>> */
    public function popular(int $limit = 5, bool $publicOnly = true): array;
    /** @return array<int,array<string,mixed>> */
    public function related(int $articleId, int $limit = 4): array;

    public function recordFeedback(int $articleId, bool $helpful, ?int $userId, ?string $comment, ?string $ip): void;

    // --- Management ---------------------------------------------------------
    /** @return array<int,array<string,mixed>> */
    public function allArticles(int $page, int $perPage, ?string $search = null): array;
    public function countArticles(?string $search = null): int;
    /** @param array<string,mixed> $data */
    public function createArticle(array $data): int;
    /** @param array<string,mixed> $data */
    public function updateArticle(int $id, array $data): void;
    public function deleteArticle(int $id): void;
    /** @param array<string,mixed> $data */
    public function createCategory(array $data): int;
}
