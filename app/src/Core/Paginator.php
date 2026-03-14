<?php
declare(strict_types=1);

namespace Zaco\Core;

/**
 * Pagination Helper Class
 * Centralizes pagination logic across all controllers
 */
final class Paginator
{
    private int $page;
    private int $perPage;
    private int $total;
    private int $totalPages;
    private int $offset;

    /** @var array<string,mixed> */
    private array $queryParams;

    private string $baseUrl;

    /**
     * Create paginator from request parameters
     * @param array<string,mixed> $queryParams Additional query params to preserve
     */
    public function __construct(
        int $total,
        int $page = 1,
        int $perPage = 25,
        array $queryParams = [],
        string $baseUrl = ''
    ) {
        $this->total = max(0, $total);
        $this->perPage = max(1, min(100, $perPage));
        $this->totalPages = (int)ceil($this->total / $this->perPage);
        
        // Clamp page to valid range
        $this->page = max(1, min($page, max(1, $this->totalPages)));
        
        $this->offset = ($this->page - 1) * $this->perPage;
        $this->queryParams = $queryParams;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Create from GET request
     */
    public static function fromRequest(
        int $total,
        array $preserveParams = [],
        string $baseUrl = '',
        int $defaultPerPage = 25
    ): self {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? $defaultPerPage)));

        $queryParams = [];
        foreach ($preserveParams as $param) {
            if (isset($_GET[$param])) {
                $queryParams[$param] = $_GET[$param];
            }
        }

        return new self($total, $page, $perPage, $queryParams, $baseUrl);
    }

    /**
     * Get current page number
     */
    public function currentPage(): int
    {
        return $this->page;
    }

    /**
     * Get items per page
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get total number of items
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get total number of pages
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * Get SQL offset
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Get LIMIT clause value
     */
    public function limit(): int
    {
        return $this->perPage;
    }

    /**
     * Check if there's a previous page
     */
    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    /**
     * Check if there's a next page
     */
    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }

    /**
     * Get URL for a specific page
     */
    public function pageUrl(int $targetPage): string
    {
        $params = array_merge($this->queryParams, ['page' => max(1, $targetPage)]);
        
        // Filter out empty values
        $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
        
        $query = http_build_query($params);
        $url = $this->baseUrl;
        
        if ($query !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . $query;
        }
        
        return Http::url($url);
    }

    /**
     * Get URL for first page
     */
    public function firstPageUrl(): string
    {
        return $this->pageUrl(1);
    }

    /**
     * Get URL for last page
     */
    public function lastPageUrl(): string
    {
        return $this->pageUrl($this->totalPages);
    }

    /**
     * Get URL for previous page
     */
    public function previousPageUrl(): string
    {
        return $this->pageUrl($this->page - 1);
    }

    /**
     * Get URL for next page
     */
    public function nextPageUrl(): string
    {
        return $this->pageUrl($this->page + 1);
    }

    /**
     * Get array of page numbers for rendering pagination links
     * @return int[]
     */
    public function pageRange(int $windowSize = 2): array
    {
        if ($this->totalPages <= 1) {
            return [];
        }

        $start = max(1, $this->page - $windowSize);
        $end = min($this->totalPages, $this->page + $windowSize);

        return range($start, $end);
    }

    /**
     * Get start item number (1-based)
     */
    public function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }
        return $this->offset + 1;
    }

    /**
     * Get end item number
     */
    public function to(): int
    {
        if ($this->total === 0) {
            return 0;
        }
        return min($this->offset + $this->perPage, $this->total);
    }

    /**
     * Get summary text (e.g., "عرض 1 إلى 25 من 100")
     */
    public function summary(string $locale = 'ar'): string
    {
        if ($this->total === 0) {
            return $locale === 'ar' ? 'لا توجد نتائج' : 'No results';
        }

        if ($locale === 'ar') {
            return sprintf(
                'عرض %d إلى %d من %d',
                $this->from(),
                $this->to(),
                $this->total
            );
        }

        return sprintf(
            'Showing %d to %d of %d',
            $this->from(),
            $this->to(),
            $this->total
        );
    }

    /**
     * Render Bootstrap pagination HTML
     */
    public function render(string $locale = 'ar'): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center mb-0">';

        // First page
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s" title="%s">&laquo;</a></li>',
            $this->page === 1 ? 'disabled' : '',
            $this->page === 1 ? '#' : Http::e($this->firstPageUrl()),
            $locale === 'ar' ? 'الأولى' : 'First'
        );

        // Previous page
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s" title="%s">&lsaquo;</a></li>',
            !$this->hasPrevious() ? 'disabled' : '',
            $this->hasPrevious() ? Http::e($this->previousPageUrl()) : '#',
            $locale === 'ar' ? 'السابقة' : 'Previous'
        );

        // Page numbers
        $range = $this->pageRange(2);
        
        // Show first page with ellipsis if needed
        if (!empty($range) && $range[0] > 1) {
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">1</a></li>',
                Http::e($this->pageUrl(1))
            );
            if ($range[0] > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        foreach ($range as $pageNum) {
            $html .= sprintf(
                '<li class="page-item %s"><a class="page-link" href="%s">%d</a></li>',
                $pageNum === $this->page ? 'active' : '',
                Http::e($this->pageUrl($pageNum)),
                $pageNum
            );
        }

        // Show last page with ellipsis if needed
        if (!empty($range) && end($range) < $this->totalPages) {
            if (end($range) < $this->totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= sprintf(
                '<li class="page-item"><a class="page-link" href="%s">%d</a></li>',
                Http::e($this->pageUrl($this->totalPages)),
                $this->totalPages
            );
        }

        // Next page
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s" title="%s">&rsaquo;</a></li>',
            !$this->hasNext() ? 'disabled' : '',
            $this->hasNext() ? Http::e($this->nextPageUrl()) : '#',
            $locale === 'ar' ? 'التالية' : 'Next'
        );

        // Last page
        $html .= sprintf(
            '<li class="page-item %s"><a class="page-link" href="%s" title="%s">&raquo;</a></li>',
            $this->page === $this->totalPages ? 'disabled' : '',
            $this->page === $this->totalPages ? '#' : Http::e($this->lastPageUrl()),
            $locale === 'ar' ? 'الأخيرة' : 'Last'
        );

        $html .= '</ul></nav>';

        return $html;
    }

    /**
     * Export pagination data for use in views
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'totalPages' => $this->totalPages,
            'offset' => $this->offset,
            'from' => $this->from(),
            'to' => $this->to(),
            'hasPrevious' => $this->hasPrevious(),
            'hasNext' => $this->hasNext(),
        ];
    }
}
