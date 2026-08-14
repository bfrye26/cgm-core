import { useMemo, useState } from 'react';
import { Download, Upload, RotateCcw, Copy, FileDown } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Textarea, Select, Label, NodeChip, Notice, PageLoader } from '@/components/ui';
import { useConfigExport, useConfigBackups, useConfigDiff, useConfigImport, useConfigRollback } from '@/lib/hooks';
import { caps } from '@/lib/api';

interface DiffSet {
  add: string[];
  update: string[];
  remove: string[];
  unchanged: string[];
  protected: string[];
}
interface DiffPreview {
  valid?: boolean;
  errors?: string[];
  warnings?: string[];
  mode?: string;
  counts?: { queries: number; relationships: number };
  destructive?: boolean;
  diff?: { queries: DiffSet; relationships: DiffSet };
  preview?: DiffPreview;
}

function ChangeList({ title, items, tone }: { title: string; items: string[]; tone: 'indigo' | 'amber' | 'rust' | 'neutral' | 'pine' | 'gold' }) {
  if (!items.length) return null;
  return (
    <div>
      <p className="eyebrow mb-1">{title}</p>
      <div className="flex flex-wrap gap-1.5">
        {items.map((id) => <NodeChip key={id} tone={tone} className="font-mono">{id}</NodeChip>)}
      </div>
    </div>
  );
}

function DiffView({ preview }: { preview: DiffPreview }) {
  const p = preview.preview ?? preview;
  const diff = p.diff;
  if (!p.valid && p.errors?.length) {
    return <Notice tone="rust">{p.errors.join(' · ')}</Notice>;
  }
  if (!diff) return <Notice tone="gold">No diff available.</Notice>;

  const totals = (d: DiffSet) => d.add.length + d.update.length + d.remove.length;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-3">
        <Notice tone={p.destructive ? 'gold' : 'pine'} className="flex-1">
          Mode: <strong>{p.mode}</strong> · Queries: {p.counts?.queries ?? 0} · Relationships: {p.counts?.relationships ?? 0}
          {p.destructive ? ' · destructive (removals)' : ''}
        </Notice>
        {p.warnings && p.warnings.length > 0 && <Notice tone="gold" className="flex-1">{p.warnings.join(' · ')}</Notice>}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="space-y-3">
          <p className="font-display text-[14px] font-semibold text-ink">Queries ({totals(diff.queries)} changed)</p>
          <ChangeList title="Add" items={diff.queries.add} tone="pine" />
          <ChangeList title="Update" items={diff.queries.update} tone="amber" />
          <ChangeList title="Remove" items={diff.queries.remove} tone="rust" />
          <ChangeList title="Protected (code-managed)" items={diff.queries.protected} tone="neutral" />
        </div>
        <div className="space-y-3">
          <p className="font-display text-[14px] font-semibold text-ink">Relationships ({totals(diff.relationships)} changed)</p>
          <ChangeList title="Add" items={diff.relationships.add} tone="pine" />
          <ChangeList title="Update" items={diff.relationships.update} tone="amber" />
          <ChangeList title="Remove" items={diff.relationships.remove} tone="rust" />
          <ChangeList title="Protected (code-managed)" items={diff.relationships.protected} tone="neutral" />
        </div>
      </div>
    </div>
  );
}

export function ConfigurationPage() {
  const canManage = caps().manageConfig || caps().manage;
  const exportQuery = useConfigExport(canManage);
  const backupsQuery = useConfigBackups(canManage);
  const diffMutation = useConfigDiff();
  const importMutation = useConfigImport();
  const rollbackMutation = useConfigRollback();

  const [json, setJson] = useState('');
  const [mode, setMode] = useState<'merge' | 'replace'>('merge');
  const [preview, setPreview] = useState<DiffPreview | null>(null);
  const [copied, setCopied] = useState(false);

  const exportJson = useMemo(() => (exportQuery.data ? JSON.stringify(exportQuery.data, null, 2) : ''), [exportQuery.data]);

  if (exportQuery.isLoading || backupsQuery.isLoading) return <PageLoader />;

  const backups = backupsQuery.data?.backups ?? [];

  const copyExport = async () => {
    await navigator.clipboard.writeText(exportJson);
    setCopied(true);
    toast.success('Configuration copied.');
    setTimeout(() => setCopied(false), 2000);
  };

  const downloadExport = () => {
    const blob = new Blob([exportJson], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `cgm-core-config-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  const onDiff = async () => {
    try {
      const config = JSON.parse(json);
      const res = (await diffMutation.mutateAsync({ config, mode })) as DiffPreview;
      setPreview(res);
    } catch {
      toast.error('Invalid JSON.');
    }
  };

  const onImport = async (dry: boolean) => {
    try {
      const config = JSON.parse(json);
      const res = (await importMutation.mutateAsync({ config, mode, dry_run: dry })) as DiffPreview;
      setPreview(res);
      toast.success(dry ? 'Dry run complete.' : 'Configuration applied.');
      backupsQuery.refetch();
      exportQuery.refetch();
    } catch {
      toast.error('Invalid JSON.');
    }
  };

  const onRollback = async (id: string) => {
    await rollbackMutation.mutateAsync(id);
    toast.success('Rolled back.');
    backupsQuery.refetch();
    exportQuery.refetch();
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Versioned definitions</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Configuration</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Export, diff and import WordPress-managed saved queries and Core-owned relationship definitions. Provider data is never exported.</p>
      </div>

      {!canManage && <Notice tone="gold">You do not have permission to manage configuration.</Notice>}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <Card className="rise" style={{ animationDelay: '60ms' }}>
          <CardHeader
            title="Export"
            desc="Current Core-managed configuration — download to promote to another environment"
            action={
              <div className="flex gap-1">
                <Button variant="outline" size="sm" icon={<Copy className="h-3.5 w-3.5" />} onClick={copyExport}>{copied ? 'Copied' : 'Copy'}</Button>
                <Button variant="outline" size="sm" icon={<FileDown className="h-3.5 w-3.5" />} onClick={downloadExport}>Download</Button>
              </div>
            }
          />
          <CardBody>
            <Textarea rows={20} readOnly value={exportJson} className="font-mono text-[12px]" />
            <p className="mt-2 text-[12px] text-ink-faint">Promote: download here, then paste the JSON into the import side of the target site (staging → production).</p>
          </CardBody>
        </Card>

        <div className="space-y-6">
          <Card className="rise" style={{ animationDelay: '120ms' }}>
            <CardHeader title="Import / sync" desc="Preview first — replace mode can remove definitions not present in the import" />
            <CardBody className="space-y-3">
              <div>
                <Label>Configuration JSON</Label>
                <Textarea rows={12} value={json} onChange={(e) => setJson(e.target.value)} placeholder='{"queries": {...}, "relationships": {...}}' className="font-mono text-[12px]" disabled={!canManage} />
              </div>
              <div>
                <Label>Mode</Label>
                <Select value={mode} onChange={(e) => setMode(e.target.value as 'merge' | 'replace')} disabled={!canManage}>
                  <option value="merge">Merge</option>
                  <option value="replace">Replace Core-managed configuration</option>
                </Select>
              </div>
              {canManage && (
                <div className="flex gap-2">
                  <Button variant="outline" icon={<Download className="h-4 w-4" />} onClick={onDiff} loading={diffMutation.isPending}>Validate &amp; diff</Button>
                  <Button variant="secondary" icon={<Upload className="h-4 w-4" />} onClick={() => onImport(true)} loading={importMutation.isPending}>Dry run</Button>
                  <Button icon={<Upload className="h-4 w-4" />} onClick={() => onImport(false)} loading={importMutation.isPending}>Apply</Button>
                </div>
              )}
            </CardBody>
          </Card>

          <Card className="rise" style={{ animationDelay: '180ms' }}>
            <CardHeader title="Rollback points" desc="A backup is created immediately before every real import" />
            <CardBody>
              {backups.length === 0 ? (
                <p className="text-[13px] text-ink-faint">No configuration backups yet.</p>
              ) : (
                <div className="space-y-1">
                  {backups.map((b) => (
                    <div key={b.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                      <NodeChip tone="neutral">{b.id}</NodeChip>
                      <div className="min-w-0 flex-1">
                        <span className="font-mono text-[11px] text-ink-faint">{b.created}</span>
                      </div>
                      {canManage && (
                        <Button variant="outline" size="sm" icon={<RotateCcw className="h-3.5 w-3.5" />} onClick={() => onRollback(b.id)} loading={rollbackMutation.isPending}>Rollback</Button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </CardBody>
          </Card>
        </div>
      </div>

      {preview && (
        <Card className="rise" style={{ animationDelay: '220ms' }}>
          <CardHeader title="Preview" desc="Change summary" />
          <CardBody>
            <DiffView preview={preview} />
          </CardBody>
        </Card>
      )}
    </div>
  );
}
