import AppLayout from '@/layouts/app-layout';
import { search } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Search',
        href: search().url,
    },
];

interface Tag {
    key: string;
    value: string;
}

interface SearchResult {
    id: number;
    url: string;
    thumbnail_url: string;
    similarity: number;
    tags: Tag[];
}

interface Props {
    query: string;
    results: SearchResult[];
}

export default function Search({ query: initialQuery, results }: Props) {
    const [query, setQuery] = useState(initialQuery);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const searchUrl = `/search?q=${encodeURIComponent(query)}`;
        router.get(searchUrl);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Semantic Search" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
                {/* Search Header */}
                <div className="flex flex-col gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Semantic Search</h1>
                        <p className="text-sm text-muted-foreground">
                            Search your images using natural language
                        </p>
                    </div>

                    {/* Search Form */}
                    <form onSubmit={handleSearch} className="flex gap-2">
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="e.g., 'red nike shoes' or 'toy story dvd'"
                            className="flex-1 rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <button
                            type="submit"
                            className="rounded-lg bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            Search
                        </button>
                    </form>
                </div>

                {/* Results */}
                {initialQuery && (
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold">
                                Results for "{initialQuery}"
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {results.length} images found
                            </span>
                        </div>

                        {results.length > 0 ? (
                            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {results.map((result) => (
                                    <div
                                        key={result.id}
                                        className="flex flex-col gap-2 overflow-hidden rounded-lg border border-sidebar-border bg-card p-2"
                                    >
                                        {/* Image */}
                                        <div className="relative aspect-square overflow-hidden rounded-md bg-muted">
                                            <img
                                                src={result.thumbnail_url}
                                                alt=""
                                                className="size-full object-cover"
                                            />
                                        </div>

                                        {/* Similarity Score */}
                                        <div className="flex items-center justify-between px-1">
                                            <span className="text-xs font-medium text-muted-foreground">
                                                Match
                                            </span>
                                            <span className="text-xs font-semibold text-foreground">
                                                {result.similarity}%
                                            </span>
                                        </div>

                                        {/* Tags */}
                                        <div className="flex flex-wrap gap-1">
                                            {result.tags.slice(0, 3).map((tag, idx) => (
                                                <span
                                                    key={idx}
                                                    className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                                >
                                                    {tag.key}: {tag.value}
                                                </span>
                                            ))}
                                            {result.tags.length > 3 && (
                                                <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                    +{result.tags.length - 3}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-sidebar-border py-12">
                                <p className="text-sm text-muted-foreground">
                                    No results found. Try a different search query.
                                </p>
                            </div>
                        )}
                    </div>
                )}

                {/* Empty State */}
                {!initialQuery && (
                    <div className="flex flex-1 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-sidebar-border py-12">
                        <p className="text-sm text-muted-foreground">
                            Enter a search query to get started
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Try: "pokemon cards", "red shoes", or "toy story dvd"
                        </p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
