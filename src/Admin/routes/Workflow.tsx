import { useState } from 'react';
import { GitCommitHorizontal, ArrowRight, CalendarClock, X, Plus, Save, Trash2, Timer } from 'lucide-react';
import { toast } from 'sonner';
import { Card, CardHeader, CardBody, Button, Select, Label, NodeChip, PageLoader, Input, Checkbox } from '@/components/ui';
import { useBootstrap, useWorkflowStates, useWorkflowTransition, useSearchObjects, useScheduledWorkflow, useScheduleWorkflow, useCancelScheduledWorkflow, useAutoTransitions, useSaveAutoTransitions, useRunAutoTransitions, type AutoTransitionRule } from '@/lib/hooks';

const COLOR_TONE: Record<string, 'indigo' | 'amber' | 'neutral' | 'pine' | 'gold' | 'rust'> = {
  neutral: 'neutral', gold: 'gold', pine: 'pine', rust: 'rust', indigo: 'indigo', amber: 'amber',
};

export function WorkflowPage() {
  const bootstrap = useBootstrap();
  const statesQuery = useWorkflowStates();
  const transition = useWorkflowTransition();
  const scheduledQuery = useScheduledWorkflow();
  const schedule = useScheduleWorkflow();
  const cancelScheduled = useCancelScheduledWorkflow();
  const autoQuery = useAutoTransitions();
  const saveAuto = useSaveAutoTransitions();
  const runAuto = useRunAutoTransitions();

  const [contentType, setContentType] = useState('post');
  const [search, setSearch] = useState('');
  const [objectId, setObjectId] = useState(0);
  const [state, setState] = useState('');
  const [scheduleState, setScheduleState] = useState('');
  const [scheduleAt, setScheduleAt] = useState('');
  const [autoRules, setAutoRules] = useState<AutoTransitionRule[] | null>(null);

  const objectSearch = useSearchObjects(contentType, search);

  if (bootstrap.isLoading || statesQuery.isLoading || autoQuery.isLoading) return <PageLoader />;

  const contentTypes = bootstrap.data?.contentTypes ?? [];
  const states = statesQuery.data?.states ?? [];
  const scheduled = scheduledQuery.data?.items ?? [];
  const rules = autoRules ?? autoQuery.data?.rules ?? [];
  const selectedLabel = (objectSearch.data?.items ?? []).find((o) => o.id === objectId)?.label ?? `#${objectId}`;

  const blankRule = (): AutoTransitionRule => ({ id: `rule_${Date.now()}`, content_types: ['*'], from_state: 'published', to_state: 'archived', after_days: 30, enabled: false });
  const updateRule = (i: number, patch: Partial<AutoTransitionRule>) => setAutoRules(rules.map((r, j) => (j === i ? { ...r, ...patch } : r)));
  const addRule = () => setAutoRules([...rules, blankRule()]);
  const removeRule = (i: number) => setAutoRules(rules.filter((_, j) => j !== i));
  const onSaveAuto = async () => { await saveAuto.mutateAsync(rules); toast.success('Auto-transition rules saved.'); setAutoRules(null); };
  const onRunAuto = async () => { const r = await runAuto.mutateAsync(); toast.success(`${r.transitioned} object(s) transitioned.`); };

  const doTransition = async () => {
    if (!objectId || !state) { toast.error('Choose an object and a state.'); return; }
    await transition.mutateAsync({ object_id: objectId, state });
    toast.success(`Moved ${selectedLabel} to ${state}.`);
  };

  const doSchedule = async () => {
    if (!objectId || !scheduleState || !scheduleAt) { toast.error('Choose an object, a state and a time.'); return; }
    await schedule.mutateAsync({ object_id: objectId, state: scheduleState, at: scheduleAt });
    toast.success('Transition scheduled.');
    setScheduleState('');
    setScheduleAt('');
  };

  return (
    <div className="space-y-6">
      <div className="rise">
        <p className="eyebrow">Editorial workflow</p>
        <h2 className="mt-1 font-display text-3xl font-bold tracking-tight text-ink">Workflow</h2>
        <p className="mt-1 text-[13px] text-ink-faint">Registrable editorial states layered over WP status — queryable, contextual, transitionable, and schedulable.</p>
      </div>

      <Card className="rise" style={{ animationDelay: '60ms' }}>
        <CardHeader title="States" desc="Available editorial states and their allowed transitions" action={<GitCommitHorizontal className="h-4 w-4 text-indigo-bright" />} />
        <CardBody>
          <div className="flex flex-wrap items-center gap-3">
            {states.map((s, i) => (
              <span key={s.id} className="flex items-center gap-3">
                <NodeChip tone={COLOR_TONE[s.color] ?? 'neutral'}>{s.label}</NodeChip>
                {i < states.length - 1 && <ArrowRight className="h-4 w-4 text-ink-faint" />}
              </span>
            ))}
          </div>
          <div className="mt-4 space-y-2">
            {states.map((s) => (
              <div key={s.id} className="flex items-center gap-3 rounded-md px-2 py-1.5">
                <NodeChip tone={COLOR_TONE[s.color] ?? 'neutral'}>{s.id}</NodeChip>
                <span className="font-mono text-[11px] text-ink-faint">→ {s.transitions.join(', ') || '—'}</span>
              </div>
            ))}
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '120ms' }}>
        <CardHeader title="Select an object" desc="Search and pick the object to transition or schedule" />
        <CardBody>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div>
              <Label>Content type</Label>
              <Select value={contentType} onChange={(e) => { setContentType(e.target.value); setObjectId(0); }}>
                {contentTypes.filter((ct) => ct.kind === 'post').map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
              </Select>
            </div>
            <div>
              <Label>Find object</Label>
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search title…" />
            </div>
            <div>
              <Label>Object</Label>
              <Select value={objectId} onChange={(e) => setObjectId(Number(e.target.value))}>
                <option value={0}>— choose —</option>
                {(objectSearch.data?.items ?? []).map((o) => <option key={o.id} value={o.id}>{String(o.label ?? `#${o.id}`)}</option>)}
              </Select>
            </div>
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '160ms' }}>
        <CardHeader title="Transition now" desc="Move the selected object to a new state immediately" />
        <CardBody>
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56">
              <Label>New state</Label>
              <Select value={state} onChange={(e) => setState(e.target.value)}>
                <option value="">— choose —</option>
                {states.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
              </Select>
            </div>
            <Button icon={<GitCommitHorizontal className="h-4 w-4" />} onClick={doTransition} loading={transition.isPending} disabled={!objectId}>Transition</Button>
          </div>
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '200ms' }}>
        <CardHeader title="Schedule a transition" desc="Schedule a future publish, unpublish, archive or review (Drupal Scheduler parity)" action={<CalendarClock className="h-4 w-4 text-indigo-bright" />} />
        <CardBody className="space-y-3">
          <div className="flex flex-wrap items-end gap-3">
            <div className="w-56">
              <Label>New state</Label>
              <Select value={scheduleState} onChange={(e) => setScheduleState(e.target.value)}>
                <option value="">— choose —</option>
                {states.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
              </Select>
            </div>
            <div className="w-64">
              <Label>Date &amp; time</Label>
              <Input type="datetime-local" value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} />
            </div>
            <Button icon={<CalendarClock className="h-4 w-4" />} onClick={doSchedule} loading={schedule.isPending} disabled={!objectId}>Schedule</Button>
          </div>

          {scheduled.length > 0 && (
            <div className="mt-3 space-y-1 border-t border-line pt-3">
              {scheduled.map((s) => (
                <div key={s.id} className="flex items-center gap-3 rounded-md px-2 py-2 transition hover:bg-surface-2/60">
                  <NodeChip tone="indigo">{s.post_title || `#${s.post_id}`}</NodeChip>
                  <NodeChip tone={COLOR_TONE[states.find((x) => x.id === s.state)?.color ?? 'neutral'] ?? 'neutral'}>{s.state_label || s.state}</NodeChip>
                  <span className="min-w-0 flex-1 font-mono text-[11px] text-ink-faint">{new Date(s.at * 1000).toLocaleString()}</span>
                  <Button variant="ghost" size="sm" icon={<X className="h-3.5 w-3.5" />} onClick={() => cancelScheduled.mutate(s.id)} aria-label="Cancel scheduled transition" />
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      <Card className="rise" style={{ animationDelay: '240ms' }}>
        <CardHeader
          title="Auto-transitions"
          desc="Auto-expire and auto-archive — move objects automatically after N days in a state"
          action={<Button variant="outline" size="sm" icon={<Timer className="h-3.5 w-3.5" />} onClick={onRunAuto} loading={runAuto.isPending}>Run now</Button>}
        />
        <CardBody className="space-y-3">
          {rules.length === 0 ? (
            <p className="text-[13px] text-ink-faint">No auto-transition rules. Add one to auto-archive or auto-unpublish content.</p>
          ) : (
            rules.map((rule, i) => (
              <div key={rule.id} className="grid grid-cols-1 gap-2 rounded-md border border-line bg-surface-2/40 p-3 md:grid-cols-[140px_140px_140px_110px_auto]">
                <div>
                  <Label>Content</Label>
                  <Select value={rule.content_types.includes('*') ? '*' : rule.content_types[0] ?? '*'} onChange={(e) => updateRule(i, { content_types: e.target.value === '*' ? ['*'] : [e.target.value] })} className="h-8 text-[12.5px]">
                    <option value="*">All content types</option>
                    {contentTypes.filter((ct) => ct.kind === 'post').map((ct) => <option key={ct.id} value={ct.id}>{ct.plural_label ?? ct.label}</option>)}
                  </Select>
                </div>
                <div>
                  <Label>From</Label>
                  <Select value={rule.from_state} onChange={(e) => updateRule(i, { from_state: e.target.value })} className="h-8 text-[12.5px]">
                    {states.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                  </Select>
                </div>
                <div>
                  <Label>To</Label>
                  <Select value={rule.to_state} onChange={(e) => updateRule(i, { to_state: e.target.value })} className="h-8 text-[12.5px]">
                    {states.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
                  </Select>
                </div>
                <div>
                  <Label>After (days)</Label>
                  <Input type="number" min={1} value={rule.after_days} onChange={(e) => updateRule(i, { after_days: Math.max(1, Number(e.target.value) || 30) })} className="h-8" />
                </div>
                <div className="flex items-end justify-between gap-2">
                  <div className="flex flex-col justify-end">
                    <Checkbox label="Enabled" checked={rule.enabled} onChange={(v) => updateRule(i, { enabled: v })} />
                  </div>
                  <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => removeRule(i)} className="h-8 w-8 p-0 text-rust" aria-label="Remove rule" />
                </div>
              </div>
            ))
          )}
          <div className="flex gap-2 border-t border-line pt-3">
            <Button variant="outline" icon={<Plus className="h-4 w-4" />} onClick={addRule}>Add rule</Button>
            <Button icon={<Save className="h-4 w-4" />} onClick={onSaveAuto} loading={saveAuto.isPending}>Save rules</Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
