import { useMemo, useState } from 'react';
import { Share2 } from 'lucide-react';
import { Card, CardHeader, CardBody, Input, Select, Label, PageLoader, EmptyState } from '@/components/ui';
import { useBootstrap, useGraph, useSearchObjects } from '@/lib/hooks';

export function GraphPage() {
  const bootstrap = useBootstrap();
  const [contentType, setContentType] = useState('post');
  const [search, setSearch] = useState('');
  const [objectId, setObjectId] = useState(0);
  const [depth, setDepth] = useState(1);

  const objectSearch = useSearchObjects(contentType, search);
  const graph = useGraph(contentType, objectId, depth);

  const layout = useMemo(() => {
    const nodes = graph.data?.nodes ?? [];
    const root = nodes.find((n) => n.id === objectId);
    const others = nodes.filter((n) => n.id !== objectId);
    const positions: Record<string, { x: number; y: number }> = {};
    const W = 760; const H = 460; const cx = W / 2; const cy = H / 2;
    if (root) positions[`${root.type}:${root.id}`] = { x: cx, y: cy };
    const radius = Math.min(W, H) / 2 - 60;
    others.forEach((n, i) => {
      const angle = (i / Math.max(1, others.length)) * Math.PI * 2 - Math.PI / 2;
      positions[`${n.type}:${n.id}`] = { x: cx + radius * Math.cos(angle), y: cy + radius * Math.sin(angle) };
    });
    return positions;
  }, [graph.data, objectId]);

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const nodes = graph.data?.nodes ?? [];
  const edges = graph.data?.edges ?? [];

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Relationship graph</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Graph</h2>
        <p className="mt-1 text-[13px] text-ink-faint">How an object connects to everything else — nodes are objects, edges are relationships.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="Select an object" desc="The graph expands from this root" />
        <CardBody>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
              <Label>Content type</Label>
              <Select value={contentType} onChange={(e) => { setContentType(e.target.value); setObjectId(0); }}>
                {contentTypes.map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
              </Select>
            </div>
            <div>
              <Label>Find object</Label>
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search…" />
            </div>
            <div>
              <Label>Object</Label>
              <Select value={objectId} onChange={(e) => setObjectId(Number(e.target.value))}>
                <option value={0}>— choose —</option>
                {(objectSearch.data?.items ?? []).map((o) => <option key={o.id} value={o.id}>{String(o.label ?? `#${o.id}`)}</option>)}
              </Select>
            </div>
            <div>
              <Label>Depth</Label>
              <Select value={depth} onChange={(e) => setDepth(Number(e.target.value))}>
                <option value={1}>1 hop</option>
                <option value={2}>2 hops</option>
                <option value={3}>3 hops</option>
              </Select>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="Graph" desc={`${nodes.length} nodes · ${edges.length} edges`} action={<Share2 className="h-4 w-4 text-indigo-bright" />} />
        <CardBody>
          {objectId === 0 ? (
            <EmptyState icon={<Share2 className="h-5 w-5" />} title="Select an object" desc="Pick an object above to visualize its relationships." />
          ) : (
            <svg viewBox="0 0 760 460" className="w-full">
              {edges.map((e, i) => {
                const s = layout[e.source]; const t = layout[e.target];
                if (!s || !t) return null;
                return <line key={i} x1={s.x} y1={s.y} x2={t.x} y2={t.y} stroke="hsl(var(--line-strong))" strokeWidth={1.5} />;
              })}
              {nodes.map((n) => {
                const p = layout[`${n.type}:${n.id}`];
                if (!p) return null;
                const isRoot = n.id === objectId;
                return (
                  <g key={`${n.type}:${n.id}`} transform={`translate(${p.x},${p.y})`}>
                    <circle r={isRoot ? 14 : 9} fill={isRoot ? 'hsl(var(--amber-bright))' : 'hsl(var(--indigo))'} stroke="hsl(var(--surface))" strokeWidth={2} />
                    <text y={isRoot ? 28 : 20} textAnchor="middle" className="fill-[hsl(var(--ink))] font-mono text-[10px]">{n.label}</text>
                  </g>
                );
              })}
            </svg>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
