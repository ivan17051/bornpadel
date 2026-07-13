@php
    $currentSort = request('sort');
    $currentDir = request('dir', 'asc') === 'desc' ? 'desc' : 'asc';
    $isActive = $currentSort === $column;
    $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
    $params = array_filter(array_merge(
        request()->only(['id_turnamen', 'search', 'status']),
        ! empty($preserveTab) ? ['tab' => $preserveTab] : [],
        ['sort' => $column, 'dir' => $nextDir]
    ));
    $url = ($filterRoute ?? request()->url()) . '?' . http_build_query($params);
    $icon = $isActive
        ? ($currentDir === 'asc' ? 'bi-arrow-up-short' : 'bi-arrow-down-short')
        : 'bi-arrow-down-up';
@endphp
<th @if (! empty($class)) class="{{ $class }}" @endif scope="col">
    <a href="{{ $url }}" class="pemain-table-sort-link text-decoration-none text-body">
        <span>{{ $label }}</span>
        <i class="bi {{ $icon }} pemain-table-sort-icon {{ $isActive ? 'is-active' : '' }}" aria-hidden="true"></i>
    </a>
</th>
