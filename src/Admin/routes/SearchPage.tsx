import { useState } from 'react';
import { Search as SearchIcon } from 'lucide-react';
import { Card, CardHeader, CardBody, Input, Select, NodeChip, PageLoader, EmptyState, Checkbox } from '@/components/ui';
import { useBootstrap, useFacets, useSearch } from '@/lib/hooks';

export function SearchPage() {
  const bootstrap = useBootstrap();
  const [q, setQ] = useState('');
  const [contentType, setContentType] = useState('post');
  const [filters, setFilters] = useState<Record<string, string[]>>({});

  const facetsQuery = useFacets(contentType);
  const search = useSearch(q, contentType, filters, q.length > 0);

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const facets = facetsQuery.data?.facets ?? [];

  const toggleFilter = (facetId: string, value: string) => {
    setFilters((prev) => {
      const cur = prev[facetId] ?? [];
      const next = cur.includes(value) ? cur.filter((v) => v !== value) : [...cur, value];
      const out = { ...prev };
      if (next.length) out[facetId] = next;
      else delete out[facetId];
      return out;
    });
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Unified search</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Search</h2>
        <p className="mt-1 text-[13px] text-ink-faint">One search facade over content, with faceted filtering — powered by the native engine, or Typesense when registered.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardBody>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_220px]">
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search content…" className="h-10" />
            <Select value={contentType} onChange={(e) => setContentType(e.target.value)} className="h-10">
              {contentTypes.filter((ct) => ct.kind === 'post').map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
            </Select>
          </div>
        </CardBody>
      </Card>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {facets.length > 0 && (
          <Card className="rise lg:col-span-1" style={{ animationDelay: '120ms' }}>
            <CardHeader title="Refine" desc="Faceted filters" />
            <CardBody className="space-y-4">
              {facets.map((facet) => (
                <div key={facet.id}>
                  <p className="eyebrow mb-1.5">{facet.label}</p>
                  <div className="space-y-1">
                    {facet.options.slice(0, 12).map((o) => (
                      <Checkbox key={o.value} label={`${o.label} (${o.count})`} checked={(filters[facet.id] ?? []).includes(o.value)} onChange={() => toggleFilter(facet.id, o.value)} />
                    ))}
                  </div>
                </div>
              ))}
            </CardBody>
          </Card>
        )}

        <Card className={`rise ${facets.length > 0 ? 'lg:col-span-3' : 'lg:col-span-4'}`} style={{ animationDelay: '180ms' }}>
          <CardHeader title={`Results${search.data ? ` · ${search.data.total}` : ''}`} desc="Across the selected content type" />
          <CardBody>
            {!q ? (
              <EmptyState icon={<SearchIcon className="h-5 w-5" />} title="Start typing to search" desc="Results and facets appear here." />
            ) : search.isLoading ? (
              <PageLoader />
            ) : (search.data?.items ?? []).length === 0 ? (
              <EmptyState icon={<SearchIcon className="h-5 w-5" />} title="No results" desc="Try a different query." />
            ) : (
              <div className="space-y-1">
                {(search.data?.items ?? []).map((item) => (
                  <div key={item.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                    <NodeChip tone="neutral">#{item.id}</NodeChip>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13.5px] font-medium text-ink">{String(item.label ?? item.title ?? `#${item.id}`)}</p>
                      {item.url && <a className="truncate font-mono text-[11px] text-indigo-ink hover:underline" href={String(item.url)}>{String(item.url)}</a>}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
