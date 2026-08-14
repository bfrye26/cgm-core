import { Plus, Trash2 } from 'lucide-react';
import { Button, Input, Select } from './ui';
import type { RuleAction } from '@/lib/hooks';

interface Props {
  actions: RuleAction[];
  onChange: (actions: RuleAction[]) => void;
  relationships: { id: string; label: string }[];
  actionDefs: { id: string; label: string }[];
}

export function ActionsEditor({ actions, onChange, relationships, actionDefs }: Props) {
  const setA = (ai: number, patch: Partial<RuleAction>) => onChange(actions.map((x, j) => (j === ai ? { ...x, ...patch } : x)));
  const remove = (ai: number) => onChange(actions.filter((_, j) => j !== ai));
  const add = () => onChange([...actions, { type: 'dispatch' }]);

  return (
    <div>
      <p className="eyebrow mb-1">Actions</p>
      {actions.map((a, ai) => (
        <div key={ai} className="mb-2 rounded-md border border-line bg-surface-2/40 p-2.5">
          <div className="flex items-center gap-2">
            <Select value={a.type} onChange={(e) => setA(ai, { type: e.target.value as RuleAction['type'] })} className="h-8 max-w-[180px] text-[12.5px]">
              <option value="dispatch">Dispatch event</option>
              <option value="reindex">Reindex</option>
              <option value="purge">Purge cache</option>
              <option value="set_meta">Set meta</option>
              <option value="set_term">Set term</option>
              <option value="add_term">Add term</option>
              <option value="set_status">Set status</option>
              <option value="add_relationship">Add relationship</option>
              <option value="notify">Notify</option>
              <option value="webhook">Webhook</option>
              <option value="schedule">Schedule</option>
              <option value="action">Call action</option>
            </Select>
            <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => remove(ai)} className="ml-auto h-8 w-8 p-0" aria-label="Remove action" />
          </div>
          <div className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
            {a.type === 'dispatch' && <Input value={a.event ?? ''} onChange={(e) => setA(ai, { event: e.target.value })} placeholder="event name" className="h-8 font-mono text-[12.5px]" />}
            {a.type === 'reindex' && <Input value={a.index ?? ''} onChange={(e) => setA(ai, { index: e.target.value })} placeholder="index id (blank = all)" className="h-8 font-mono text-[12.5px]" />}
            {a.type === 'purge' && <Input value={a.tag ?? ''} onChange={(e) => setA(ai, { tag: e.target.value })} placeholder="cache tag" className="h-8 font-mono text-[12.5px]" />}
            {a.type === 'action' && (
              <Select value={a.action ?? ''} onChange={(e) => setA(ai, { action: e.target.value })} className="h-8 text-[12.5px]">
                <option value="">— choose —</option>
                {actionDefs.map((ad) => <option key={ad.id} value={ad.id}>{ad.label}</option>)}
              </Select>
            )}
            {a.type === 'set_meta' && (<>
              <Input value={a.meta_key ?? ''} onChange={(e) => setA(ai, { meta_key: e.target.value })} placeholder="meta key" className="h-8 font-mono text-[12.5px]" />
              <Input value={a.meta_value ?? ''} onChange={(e) => setA(ai, { meta_value: e.target.value })} placeholder="value · {title} / {object_id}" className="h-8 font-mono text-[12.5px]" />
            </>)}
            {a.type === 'set_term' && (<>
              <Input value={a.taxonomy ?? ''} onChange={(e) => setA(ai, { taxonomy: e.target.value })} placeholder="taxonomy" className="h-8 font-mono text-[12.5px]" />
              <Input value={a.term ?? ''} onChange={(e) => setA(ai, { term: e.target.value })} placeholder="term id or slug" className="h-8 font-mono text-[12.5px]" />
            </>)}
            {a.type === 'set_status' && (
              <Select value={a.status ?? ''} onChange={(e) => setA(ai, { status: e.target.value })} className="h-8 text-[12.5px]">
                <option value="">— choose —</option>
                <option value="publish">publish</option>
                <option value="draft">draft</option>
                <option value="pending">pending</option>
                <option value="private">private</option>
              </Select>
            )}
            {a.type === 'add_relationship' && (<>
              <Select value={a.relationship ?? ''} onChange={(e) => setA(ai, { relationship: e.target.value })} className="h-8 text-[12.5px]">
                {relationships.map((r) => <option key={r.id} value={r.id}>{r.label}</option>)}
              </Select>
              <Input value={a.target ?? ''} onChange={(e) => setA(ai, { target: e.target.value })} placeholder="target id · {object_id}" className="h-8 font-mono text-[12.5px]" />
            </>)}
            {a.type === 'notify' && (<>
              <Input value={a.to ?? ''} onChange={(e) => setA(ai, { to: e.target.value })} placeholder="email · author · admin" className="h-8 font-mono text-[12.5px]" />
              <Input value={a.subject ?? ''} onChange={(e) => setA(ai, { subject: e.target.value })} placeholder="subject" className="h-8 text-[12.5px]" />
              <Input value={a.message ?? ''} onChange={(e) => setA(ai, { message: e.target.value })} placeholder="message · {title}" className="col-span-2 h-8 text-[12.5px]" />
            </>)}
            {a.type === 'webhook' && <Input value={a.url ?? ''} onChange={(e) => setA(ai, { url: e.target.value })} placeholder="https://… endpoint URL" className="h-8 font-mono text-[12.5px]" />}
            {a.type === 'schedule' && (<>
              <Input value={a.event ?? ''} onChange={(e) => setA(ai, { event: e.target.value })} placeholder="event to dispatch later" className="h-8 font-mono text-[12.5px]" />
              <Input type="number" min={1} value={a.delay ?? 5} onChange={(e) => setA(ai, { delay: Number(e.target.value) || 5 })} placeholder="delay (minutes)" className="h-8 text-[12.5px]" />
            </>)}
            {a.type === 'add_term' && (<>
              <Input value={a.taxonomy ?? ''} onChange={(e) => setA(ai, { taxonomy: e.target.value })} placeholder="taxonomy" className="h-8 font-mono text-[12.5px]" />
              <Input value={a.term ?? ''} onChange={(e) => setA(ai, { term: e.target.value })} placeholder="term name · {title}" className="h-8 font-mono text-[12.5px]" />
            </>)}
          </div>
        </div>
      ))}
      <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={add}>Add action</Button>
    </div>
  );
}
