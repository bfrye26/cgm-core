import { useEffect, useState } from 'react';
import { Zap, Plus, Trash2, Save, Pencil, X } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Input, Select, Label, NodeChip, PageLoader, Checkbox, EmptyState } from '@/components/ui';
import { ActionsEditor } from '@/components/actions-editor';
import { useBootstrap, useRules, useSaveRules, type Rule } from '@/lib/hooks';

const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS'];

function blankRule(): Rule {
  return { id: '', label: '', event: 'content.changed', enabled: true, conditions: [], actions: [] };
}

export function AutomationPage() {
  const bootstrap = useBootstrap();
  const rulesQuery = useRules();
  const saveRules = useSaveRules();

  const [rules, setRules] = useState<Rule[] | null>(null);
  const [editing, setEditing] = useState<number | null>(null);

  useEffect(() => {
    if (rules === null && rulesQuery.data) setRules(rulesQuery.data.rules);
  }, [rules, rulesQuery.data]);

  if (bootstrap.isLoading || rulesQuery.isLoading) return <PageLoader />;

  const fields = bootstrap.data?.fields ?? [];
  const relationships = bootstrap.data?.relationships ?? [];
  const events = rulesQuery.data?.events ?? [];
  const actionDefs = rulesQuery.data?.actions ?? [];

  const editable = rules ?? [];
  const editingRule = editing !== null ? editable[editing] : null;

  const update = (i: number, patch: Partial<Rule>) => setRules(editable.map((r, j) => (j === i ? { ...r, ...patch } : r)));
  const startNew = () => { setRules([...editable, blankRule()]); setEditing(editable.length); };
  const startEdit = (i: number) => setEditing(i);
  const cancel = () => {
    setRules(editable.filter((r, j) => !(j === editing && !r.label.trim() && r.conditions.length === 0 && r.actions.length === 0)));
    setEditing(null);
  };
  const remove = (i: number) => { setRules(editable.filter((_, j) => j !== i)); setEditing(null); };

  const onSave = async () => {
    const clean = editable.filter((r) => r.label.trim() && r.event);
    await saveRules.mutateAsync(clean);
    toast.success('Automation rules saved.');
    setEditing(null);
  };

  const renderEditor = (i: number, rule: Rule) => (
    <Card className="rise">
      <CardHeader
        title={<span className="flex items-center gap-2"><Zap className="h-4 w-4 text-amber-bright" /> {rule.label || 'New rule'}</span>}
        action={<Button variant="ghost" size="sm" icon={<X className="h-3.5 w-3.5" />} onClick={cancel}>Cancel</Button>}
      />
      <CardBody className="space-y-4">
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
          <div>
            <Label>Label</Label>
            <Input value={rule.label} onChange={(e) => update(i, { label: e.target.value })} placeholder="When a review is published…" />
          </div>
          <div>
            <Label>Event</Label>
            <Select value={rule.event} onChange={(e) => update(i, { event: e.target.value })}>
              <option value="*">Any event</option>
              {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.id}</option>)}
            </Select>
          </div>
          <div className="flex flex-col justify-end">
            <Checkbox label="Enabled" checked={rule.enabled} onChange={(v) => update(i, { enabled: v })} />
          </div>
        </div>

        <div>
          <p className="eyebrow mb-1">Conditions (all must match)</p>
          {rule.conditions.map((c, ci) => (
            <div key={ci} className="mb-2 grid grid-cols-1 gap-2 md:grid-cols-[110px_1fr_130px_1fr_auto]">
              <Select value={c.type} onChange={(e) => update(i, { conditions: rule.conditions.map((x, j) => (j === ci ? { ...x, type: e.target.value as 'field' | 'relationship' } : x)) })} className="h-8 text-[12.5px]">
                <option value="field">Field</option>
                <option value="relationship">Relationship</option>
              </Select>
              {c.type === 'relationship' ? (
                <Select value={c.relationship ?? ''} onChange={(e) => update(i, { conditions: rule.conditions.map((x, j) => (j === ci ? { ...x, relationship: e.target.value } : x)) })} className="h-8 text-[12.5px]">
                  {relationships.map((r) => <option key={r.id} value={r.id}>{r.label}</option>)}
                </Select>
              ) : (
                <Select value={c.field ?? ''} onChange={(e) => update(i, { conditions: rule.conditions.map((x, j) => (j === ci ? { ...x, field: e.target.value } : x)) })} className="h-8 text-[12.5px]">
                  {fields.map((f) => <option key={f.id} value={f.id}>{f.label}</option>)}
                </Select>
              )}
              <Select value={c.operator ?? '='} onChange={(e) => update(i, { conditions: rule.conditions.map((x, j) => (j === ci ? { ...x, operator: e.target.value } : x)) })} className="h-8 text-[12.5px]">
                {OPERATORS.map((op) => <option key={op} value={op}>{op}</option>)}
              </Select>
              <Input value={c.value ?? ''} onChange={(e) => update(i, { conditions: rule.conditions.map((x, j) => (j === ci ? { ...x, value: e.target.value } : x)) })} placeholder="Value or @token" className="h-8 font-mono text-[12.5px]" />
              <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => update(i, { conditions: rule.conditions.filter((_, j) => j !== ci) })} className="h-8 w-8 p-0" aria-label="Remove condition" />
            </div>
          ))}
          <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={() => update(i, { conditions: [...rule.conditions, { type: 'field', field: fields[0]?.id ?? '', operator: '=' }] })}>Add condition</Button>
        </div>

        <ActionsEditor actions={rule.actions} onChange={(a) => update(i, { actions: a })} relationships={relationships} actionDefs={actionDefs} />

        <div className="flex justify-end gap-2 border-t border-line pt-4">
          <Button variant="ghost" onClick={cancel}>Cancel</Button>
          <Button icon={<Save className="h-4 w-4" />} onClick={onSave} loading={saveRules.isPending}>Save rules</Button>
        </div>
      </CardBody>
    </Card>
  );

  return (
    <div className="space-y-6">
      <div className="rise flex items-end justify-between gap-3">
        <div>
          <p className="eyebrow">Event automation</p>
          <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Automation</h2>
          <p className="mt-1 text-[13px] text-ink-faint">React to content and relationship events: when X happens, do Y — reindex search, purge caches, dispatch events, call registered actions.</p>
        </div>
        {editing === null && <Button icon={<Plus className="h-4 w-4" />} onClick={startNew}>New rule</Button>}
      </div>

      {editing !== null && editingRule ? (
        renderEditor(editing, editingRule)
      ) : editable.length === 0 ? (
        <EmptyState icon={<Zap className="h-5 w-5" />} title="No rules yet" desc="Create a rule to automate a workflow." action={<Button icon={<Plus className="h-4 w-4" />} onClick={startNew}>New rule</Button>} />
      ) : (
        <div className="divide-y divide-line rounded-lg border border-line bg-surface shadow-card">
          {editable.map((rule, i) => (
            <div key={i} className="flex items-center gap-3 px-4 py-3">
              <Zap className="h-4 w-4 shrink-0 text-amber-bright" />
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13.5px] font-medium text-ink">{rule.label}</p>
                <p className="truncate font-mono text-[11px] text-ink-faint">
                  {rule.event === '*' ? 'any event' : rule.event} · {rule.conditions.length} condition{rule.conditions.length === 1 ? '' : 's'} · {rule.actions.length} action{rule.actions.length === 1 ? '' : 's'}
                </p>
              </div>
              <NodeChip tone={rule.enabled ? 'pine' : 'neutral'}>{rule.enabled ? 'on' : 'off'}</NodeChip>
              <Button variant="ghost" size="sm" icon={<Pencil className="h-3.5 w-3.5" />} onClick={() => startEdit(i)}>Edit</Button>
              <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => remove(i)} aria-label="Remove rule" />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
