import { useState } from 'react';
import { Boxes, Filter } from 'lucide-react';
import { Card, CardHeader, CardBody, PageLoader, NodeChip, Select, Label } from '@/components/ui';
import { DataTable, type Column } from '@/components/DataTable';
import { useBootstrap, type ContentType, type Field } from '@/lib/hooks';

export function ContentPage() {
  const bootstrap = useBootstrap();
  const [selected, setSelected] = useState('');

  if (bootstrap.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const allFields = bootstrap.data?.fields ?? [];
  const fields = selected ? allFields.filter((f) => (f.content_types ?? []).includes(selected)) : allFields;

  const ctColumns: Column<ContentType>[] = [
    {
      key: 'name',
      header: 'Content type',
      render: (ct) => (
        <div>
          <strong className="text-ink">{ct.plural_label ?? ct.label}</strong>
          <br />
          <code className="font-mono text-[11px] text-ink-faint">{ct.id}</code>
        </div>
      ),
    },
    { key: 'kind', header: 'Kind', render: (ct) => <NodeChip tone="neutral">{ct.kind ?? ''}</NodeChip> },
    { key: 'public', header: 'Public', render: (ct) => <NodeChip tone={ct.public ? 'pine' : 'gold'}>{ct.public ? 'Public' : 'Restricted'}</NodeChip> },
  ];

  const fieldColumns: Column<Field>[] = [
    {
      key: 'field',
      header: 'Field',
      render: (f) => (
        <div>
          <strong className="text-ink">{f.label}</strong>
          <br />
          <code className="font-mono text-[11px] text-ink-faint">{f.id}</code>
        </div>
      ),
    },
    { key: 'source', header: 'Source', render: (f) => <span className="font-mono text-[12px] text-ink-soft">{f.provider ?? ''} / {f.source ?? ''}</span> },
    { key: 'type', header: 'Type', render: (f) => <NodeChip tone="amber">{f.type ?? 'string'}</NodeChip> },
    { key: 'operators', header: 'Operators', render: (f) => <span className="font-mono text-[12px] text-ink-soft">{(f.operators ?? []).join(', ')}</span> },
  ];

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Registry</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Content</h2>
        <p className="mt-1 text-[13px] text-ink-faint">The common entity model used by relationships, queries, dynamic data and builder adapters.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="Content types" desc="Discovered WordPress objects and provider-defined types" />
        <CardBody>
          <DataTable columns={ctColumns} rows={contentTypes} rowKey={(ct) => ct.id} emptyTitle="No content types registered" />
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="Field explorer" desc="Filter the queryable field registry by content type" />
        <CardBody>
          <div className="mb-4 flex flex-wrap items-end gap-4">
            <div className="w-64">
              <Label>Content type</Label>
              <div className="flex items-center gap-2">
                <Filter className="h-4 w-4 text-ink-faint" />
                <Select value={selected} onChange={(e) => setSelected(e.target.value)}>
                  <option value="">All content types</option>
                  {contentTypes.map((ct) => (
                    <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>
                  ))}
                </Select>
              </div>
            </div>
            <span className="mb-2 flex items-center gap-2 text-[13px] text-ink-faint">
              <Boxes className="h-4 w-4" /> {fields.length} fields
            </span>
          </div>
          <DataTable columns={fieldColumns} rows={fields} rowKey={(f) => f.id} emptyTitle="No queryable fields" emptyDesc="Fields registered by WordPress, ACF, Meta Box or providers appear here." />
        </CardBody>
      </Card>
    </div>
  );
}
