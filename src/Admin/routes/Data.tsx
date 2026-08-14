import { useMemo, useState } from 'react';
import { Search, Copy, Braces, PlayCircle } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Input, NodeChip, PageLoader, EmptyState, Select, Label } from '@/components/ui';
import { useBootstrap, useSearchObjects, useResolveData, type DynamicDataDef } from '@/lib/hooks';

interface Token {
  key: string;
  label: string;
  type: string;
  group: string;
  copy: string;
}

export function DataPage() {
  const bootstrap = useBootstrap();
  const [query, setQuery] = useState('');
  const [previewType, setPreviewType] = useState('post');
  const [previewSearch, setPreviewSearch] = useState('');
  const [previewObject, setPreviewObject] = useState(0);
  const [previewKey, setPreviewKey] = useState('');

  const objectSearch = useSearchObjects(previewType, previewSearch);
  const resolved = useResolveData(previewKey, previewObject, previewType);

  const tokens = useMemo<Token[]>(() => {
    const out: Token[] = [];
    const rawDd = bootstrap.data?.dynamicData ?? [];
    const dd: DynamicDataDef[] = Array.isArray(rawDd) ? rawDd : Object.values(rawDd as Record<string, DynamicDataDef>);
    for (const d of dd) {
      const key = d.id;
      out.push({
        key,
        label: d.label ?? key,
        type: d.type ?? 'mixed',
        group: d.group ?? 'CGM Core',
        copy: `{cgm:${key}}`,
      });
    }
    for (const r of bootstrap.data?.relationships ?? []) {
      const props = r.properties ?? [];
      for (const p of props) {
        const path = `relationship.${r.id}.${p.id}`;
        out.push({
          key: path,
          label: `${r.label} · ${p.label}`,
          type: p.type ?? 'mixed',
          group: 'Relationship traversal',
          copy: `{cgm:${path}}`,
        });
      }
    }
    for (const [key, label] of Object.entries(bootstrap.data?.tokens ?? {})) {
      out.push({ key, label, type: 'context', group: 'Context', copy: key });
    }
    return out;
  }, [bootstrap.data]);

  if (bootstrap.isLoading) return <PageLoader />;

  const q = query.trim().toLowerCase();
  const filtered = q ? tokens.filter((t) => t.key.toLowerCase().includes(q) || t.label.toLowerCase().includes(q)) : tokens;

  const groups = ['CGM Core', 'Relationship traversal', 'Context', ...new Set(tokens.map((t) => t.group))].filter((g, i, a) => a.indexOf(g) === i);

  const copy = async (_key: string, snippet: string) => {
    await navigator.clipboard.writeText(snippet);
    toast.success(`Copied ${snippet}`);
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Data tokens</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Data</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Browse every dynamic-data key, relationship traversal path, and context token, then copy the snippet for Bricks, Gutenberg, or PHP.</p>
      </div>

      <div className="rise flex items-center gap-2" style={{ animationDelay: '60ms' }}>
        <Search className="h-4 w-4 text-ink-faint" />
        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search data keys and paths…" className="max-w-md" />
      </div>

      <Card className="rise" style={{ animationDelay: '80ms' }}>
        <CardHeader title="Live preview" desc="Resolve any token against a real object before you copy it" action={<PlayCircle className="h-4 w-4 text-indigo-bright" />} />
        <CardBody>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
              <Label>Content type</Label>
              <Select value={previewType} onChange={(e) => { setPreviewType(e.target.value); setPreviewObject(0); }}>
                {(bootstrap.data?.contentTypes ?? []).map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
              </Select>
            </div>
            <div>
              <Label>Find object</Label>
              <Input value={previewSearch} onChange={(e) => setPreviewSearch(e.target.value)} placeholder="Search title…" />
            </div>
            <div>
              <Label>Object</Label>
              <Select value={previewObject} onChange={(e) => setPreviewObject(Number(e.target.value))}>
                <option value={0}>— none —</option>
                {(objectSearch.data?.items ?? []).map((o) => <option key={o.id} value={o.id}>{String(o.label ?? o.title ?? `#${o.id}`)}</option>)}
              </Select>
            </div>
            <div>
              <Label>Token</Label>
              <Select value={previewKey} onChange={(e) => setPreviewKey(e.target.value)}>
                <option value="">— select a token —</option>
                {tokens.filter((t) => t.type !== 'context').map((t) => <option key={t.key} value={t.key}>{t.key}</option>)}
              </Select>
            </div>
          </div>
          {previewKey && previewObject > 0 && (
            <div className="mt-4 rounded-md border border-indigo/25 bg-indigo-soft/40 px-4 py-3">
              <p className="eyebrow">Resolved value</p>
              <p className="mt-1 font-mono text-[13px] text-indigo-ink">
                {resolved.isFetching ? 'Resolving…' : resolved.data?.value === null || resolved.data?.value === '' || resolved.data?.value === undefined ? '—' : String(resolved.data.value)}
              </p>
              {resolved.data?.type && <p className="mt-1 font-mono text-[11px] text-ink-faint">{resolved.data.type}</p>}
            </div>
          )}
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '90ms' }}>
        <CardHeader title="View modes" desc="Named presentations (display modes) consumable via [cgm_object id='…' view='…']" />
        <CardBody className="space-y-1">
          {(bootstrap.data?.viewModes ?? []).length === 0 ? (
            <p className="text-[13px] text-ink-faint">No view modes registered. Plugins register them via <code className="font-mono">cgm_register_view_mode()</code>.</p>
          ) : (
            (bootstrap.data?.viewModes ?? []).map((vm) => (
              <div key={vm.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                <NodeChip tone="indigo">{vm.label ?? vm.id}</NodeChip>
                <span className="min-w-0 flex-1 truncate font-mono text-[11px] text-ink-faint">
                  {(vm.fields ?? []).map((f) => (typeof f === 'string' ? f : f.field ?? f.path ?? '')).filter(Boolean).join(' · ') || '—'}
                </span>
                <button onClick={() => copy(vm.id, `[cgm_object id="123" view="${vm.id}"]`)} className="rounded-md px-2 py-1 text-[11px] font-medium text-indigo-ink transition hover:bg-indigo-soft">
                  <Copy className="inline h-3.5 w-3.5" /> shortcode
                </button>
              </div>
            ))
          )}
        </CardBody>
      </Card>

      {filtered.length === 0 ? (
        <EmptyState icon={<Search className="h-5 w-5" />} title="No matching tokens" desc="Try a different search term." />
      ) : (
        groups.filter((g) => filtered.some((t) => t.group === g)).map((group, gi) => (
          <Card key={group} className="rise" style={{ animationDelay: `${100 + gi * 40}ms` }}>
            <CardHeader title={group} desc={`${filtered.filter((t) => t.group === group).length} tokens`} />
            <CardBody className="space-y-1">
              {filtered.filter((t) => t.group === group).map((t) => (
                <div key={t.key} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                  <NodeChip tone={t.group === 'Relationship traversal' ? 'amber' : t.type === 'context' ? 'neutral' : 'indigo'} className="max-w-[340px]">
                    <code className="truncate font-mono text-[11px]">{t.key}</code>
                  </NodeChip>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium text-ink">{t.label}</p>
                    <p className="font-mono text-[11px] text-ink-faint">{t.type}</p>
                  </div>
                  <button onClick={() => copy(t.key, t.copy)} className="rounded-md px-2 py-1 text-[11px] font-medium text-indigo-ink transition hover:bg-indigo-soft" title={`Copy ${t.copy}`}>
                    <Copy className="inline h-3.5 w-3.5" /> {t.copy}
                  </button>
                  <button onClick={() => copy(t.key, `[cgm_data key="${t.key}"]`)} className="rounded-md px-2 py-1 text-[11px] font-medium text-ink-faint transition hover:bg-surface-2" title="Copy shortcode">
                    <Braces className="inline h-3.5 w-3.5" /> shortcode
                  </button>
                </div>
              ))}
            </CardBody>
          </Card>
        ))
      )}
    </div>
  );
}
