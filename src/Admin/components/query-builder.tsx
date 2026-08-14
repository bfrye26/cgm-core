import { useState } from 'react';
import { Plus, Trash2, CornerDownRight } from 'lucide-react';
import { Button, Select, Input, NodeChip, Notice, Label } from './ui';
import { cn } from '@/lib/utils';
import type { QueryGroup, QueryRule, QueryDefinition, Field, Relationship, Taxonomy } from '@/lib/hooks';

interface Schema {
  fields: Field[];
  relationships: Relationship[];
  taxonomies: Taxonomy[];
  tokens: Record<string, string>;
}

const relationLabel = (r: string) => (String(r).toUpperCase() === 'OR' ? 'OR' : 'AND');
const isGroup = (node: QueryRule | QueryGroup): node is QueryGroup => Array.isArray((node as QueryGroup).rules);

function fieldById(schema: Schema, id: string): Field | undefined {
  return schema.fields.find((f) => f.id === id);
}
function relById(schema: Schema, id: string): Relationship | undefined {
  return schema.relationships.find((r) => r.id === id);
}

function operatorsFor(schema: Schema, rule: QueryRule): string[] {
  switch (rule.type) {
    case 'relationship':
    case 'relationship_reverse':
      return ['EXISTS', 'NOT EXISTS', '=', '!=', 'IN', 'NOT IN'];
    case 'relationship_count':
      return ['=', '!=', '>', '>=', '<', '<='];
    case 'taxonomy':
      return ['IN', 'NOT IN', 'EXISTS', 'NOT EXISTS'];
    case 'relationship_property':
      return ['=', '!=', 'IN', 'NOT IN', 'CONTAINS'];
    case 'path':
      return ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS'];
    default: {
      const f = fieldById(schema, rule.field ?? '');
      return f?.operators?.length ? f.operators : ['=', '!='];
    }
  }
}

function valueInput(schema: Schema, rule: QueryRule, onChange: (v: QueryRule) => void) {
  const isExistential = rule.operator === 'EXISTS' || rule.operator === 'NOT EXISTS';
  if (isExistential) return null;

  const f = rule.type === 'field' ? fieldById(schema, rule.field ?? '') : undefined;
  const isDate = f?.type === 'date' || f?.type === 'datetime';
  if (rule.type === 'field' && f?.options && Object.keys(f.options).length) {
    return (
      <Select value={String(rule.value ?? '')} onChange={(e) => onChange({ ...rule, value: e.target.value })}>
        <option value="">Select…</option>
        {Object.entries(f.options).map(([v, l]) => (
          <option key={v} value={v}>{l}</option>
        ))}
      </Select>
    );
  }
  if (rule.type === 'field' && f?.type === 'boolean') {
    return (
      <Select value={String(rule.value ?? '')} onChange={(e) => onChange({ ...rule, value: e.target.value })}>
        <option value="">Select…</option>
        <option value="1">true</option>
        <option value="0">false</option>
      </Select>
    );
  }

  const tokens = Object.keys(schema.tokens);
  const placeholder = isDate ? 'now · -30d · 2024-01-01' : 'Value or @token';
  return (
    <div className="relative">
      <Input
        value={String(rule.value ?? '')}
        onChange={(e) => onChange({ ...rule, value: e.target.value })}
        placeholder={placeholder}
        list={isDate ? 'cgm-relative-dates' : 'cgm-context-tokens'}
        className="font-mono text-[12.5px]"
      />
      <datalist id="cgm-context-tokens">
        {tokens.map((t) => (
          <option key={t} value={t}>{schema.tokens[t]}</option>
        ))}
      </datalist>
      {isDate && (
        <datalist id="cgm-relative-dates">
          {['now', 'today', 'yesterday', 'tomorrow', 'this week', 'last week', 'this month', 'last month', 'this year', 'last year', '-7d', '-30d', '-3m', '-1y'].map((d) => (
            <option key={d} value={d} />
          ))}
        </datalist>
      )}
    </div>
  );
}

function RuleRow({ schema, rule, onChange, onRemove }: { schema: Schema; rule: QueryRule; onChange: (r: QueryRule) => void; onRemove: () => void }) {
  const operators = operatorsFor(schema, rule);
  const isRel = rule.type === 'relationship';
  const isRelProp = rule.type === 'relationship_property';
  const isRelRev = rule.type === 'relationship_reverse';
  const isRelCount = rule.type === 'relationship_count';
  const isTax = rule.type === 'taxonomy';
  const isPath = rule.type === 'path';

  const setType = (type: QueryRule['type']) => {
    const next: QueryRule = { type, operator: '=', value: '' };
    if (type === 'relationship' || type === 'relationship_reverse') next.operator = 'EXISTS';
    if (type === 'relationship_count') { next.operator = '>='; next.value = 1; }
    if (type === 'taxonomy') next.operator = 'IN';
    if (type === 'field') next.field = schema.fields[0]?.id ?? '';
    if (type === 'relationship' || type === 'relationship_property' || type === 'relationship_reverse' || type === 'relationship_count') next.relationship = schema.relationships[0]?.id ?? '';
    if (type === 'relationship_property') next.property = 'target_id';
    if (type === 'taxonomy') next.taxonomy = schema.taxonomies[0]?.id ?? '';
    onChange(next);
  };

  return (
    <div className="grid grid-cols-1 gap-2 rounded-md border border-line bg-surface-2/40 p-2.5 md:grid-cols-[110px_minmax(150px,1fr)_130px_minmax(140px,1fr)_auto]">
      <Select value={rule.type} onChange={(e) => setType(e.target.value as QueryRule['type'])} className="h-8 text-[12.5px]">
        <option value="field">Field</option>
        <option value="relationship">Relationship</option>
        <option value="relationship_reverse">Reverse relationship</option>
        <option value="relationship_count">Relationship count</option>
        <option value="relationship_property">Rel. property</option>
        <option value="path">Data path</option>
        <option value="taxonomy">Taxonomy</option>
      </Select>

      {isPath && (
        <Input value={rule.path ?? ''} onChange={(e) => onChange({ ...rule, path: e.target.value })} placeholder="relationship.game.primary.label" className="h-8 font-mono text-[12.5px]" />
      )}
      {(isRel || isRelProp || isRelRev || isRelCount) && (
        <div className="flex items-center gap-1">
          <Select value={rule.relationship ?? ''} onChange={(e) => onChange({ ...rule, relationship: e.target.value })} className="h-8 min-w-0 flex-1 text-[12.5px]">
            {schema.relationships.map((r) => (
              <option key={r.id} value={r.id}>{r.label}</option>
            ))}
          </Select>
          {isRelCount && (
            <button
              type="button"
              onClick={() => onChange({ ...rule, reverse: !rule.reverse })}
              title="Count forward (targets) vs reverse (sources)"
              className={cn(
                'h-8 shrink-0 rounded-md border px-2 text-[11px] font-medium transition',
                rule.reverse ? 'border-indigo bg-indigo-soft text-indigo-ink' : 'border-line-strong text-ink-faint hover:text-ink',
              )}
            >
              {rule.reverse ? 'Reverse' : 'Forward'}
            </button>
          )}
        </div>
      )}
      {isRelProp && (
        <div className="flex items-center gap-1">
          <CornerDownRight className="h-3.5 w-3.5 shrink-0 text-ink-faint" />
          <Select value={rule.property ?? 'target_id'} onChange={(e) => onChange({ ...rule, property: e.target.value })} className="h-8 text-[12.5px]">
            {((relById(schema, rule.relationship ?? '')?.properties) ?? []).map((p) => (
              <option key={p.id} value={p.id}>{p.label}</option>
            ))}
          </Select>
        </div>
      )}
      {isTax && (
        <Select value={rule.taxonomy ?? ''} onChange={(e) => onChange({ ...rule, taxonomy: e.target.value })} className="h-8 text-[12.5px]">
          {schema.taxonomies.map((t) => (
            <option key={t.id} value={t.id}>{t.label}</option>
          ))}
        </Select>
      )}
      {rule.type === 'field' && (
        <Select value={rule.field ?? ''} onChange={(e) => onChange({ ...rule, field: e.target.value })} className="h-8 text-[12.5px]">
          {schema.fields.map((f) => (
            <option key={f.id} value={f.id}>{f.label}</option>
          ))}
        </Select>
      )}

      <Select value={rule.operator ?? '='} onChange={(e) => onChange({ ...rule, operator: e.target.value })} className="h-8 text-[12.5px]">
        {operators.map((op) => (
          <option key={op} value={op}>{op}</option>
        ))}
      </Select>

      {isRelCount ? (
        <Input type="number" min={0} value={String(rule.value ?? '')} onChange={(e) => onChange({ ...rule, value: e.target.value })} placeholder="count" className="h-8" />
      ) : (
        <div className="min-w-0">{valueInput(schema, rule, onChange)}</div>
      )}

      <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={onRemove} className="h-8 w-8 p-0 text-rust hover:bg-rust-soft" title="Remove rule" aria-label="Remove rule" />
    </div>
  );
}

function GroupEditor({ schema, group, onChange, onRemove, depth = 0 }: { schema: Schema; group: QueryGroup; onChange: (g: QueryGroup) => void; onRemove?: () => void; depth?: number }) {
  const [text, setText] = useState('');

  const setRelation = (rel: 'AND' | 'OR') => onChange({ ...group, relation: rel });
  const updateRule = (i: number, r: QueryRule | QueryGroup) => {
    const rules = [...group.rules];
    rules[i] = r;
    onChange({ ...group, rules });
  };
  const removeRule = (i: number) => onChange({ ...group, rules: group.rules.filter((_, j) => j !== i) });
  const addRule = () => {
    const field = schema.fields[0]?.id ?? '';
    onChange({ ...group, rules: [...group.rules, { type: 'field', field, operator: '=', value: '' }] });
  };
  // New groups default to the opposite relation, so mixing AND with OR is one click.
  const addGroup = () => onChange({ ...group, rules: [...group.rules, { relation: relationLabel(group.relation) === 'OR' ? 'AND' : 'OR', rules: [] }] });

  const isOr = relationLabel(group.relation) === 'OR';

  return (
    <div className={cn('space-y-2', depth > 0 && 'ml-4 border-l-2 border-line pl-4')}>
      <div className="flex flex-wrap items-center gap-2">
        <div className="inline-flex overflow-hidden rounded-md border border-line-strong">
          {(['AND', 'OR'] as const).map((rel) => (
            <button
              key={rel}
              type="button"
              onClick={() => setRelation(rel)}
              className={cn(
                'px-3 py-1 text-[12px] font-semibold transition',
                relationLabel(group.relation) === rel ? 'bg-indigo text-white' : 'bg-surface text-ink-faint hover:text-ink',
              )}
            >
              {rel}
            </button>
          ))}
        </div>
        <span className="text-[12px] text-ink-faint">{isOr ? 'any of the following apply' : 'all of the following apply'}</span>
        <div className="ml-auto flex items-center gap-2">
          <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={addRule}>Add rule</Button>
          <Button variant="outline" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={addGroup} title="Add a nested AND/OR group">Add {isOr ? 'AND' : 'OR'} group</Button>
          {onRemove && (
            <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={onRemove} className="text-rust hover:bg-rust-soft">Remove group</Button>
          )}
        </div>
      </div>

      {group.rules.length === 0 && depth === 0 && (
        <div className="rounded-md border border-dashed border-line-strong bg-surface-2/40 p-4 text-center">
          <p className="text-[13px] text-ink-faint">No filters — every item matches.</p>
        </div>
      )}

      {group.rules.map((node, i) =>
        isGroup(node) ? (
          <GroupEditor key={i} schema={schema} group={node} onChange={(g) => updateRule(i, g)} onRemove={() => removeRule(i)} depth={depth + 1} />
        ) : (
          <RuleRow key={i} schema={schema} rule={node} onChange={(r) => updateRule(i, r)} onRemove={() => removeRule(i)} />
        ),
      )}

      {/* Context token quick reference */}
      {depth === 0 && (
        <div className="flex flex-wrap items-center gap-1.5 pt-1">
          {Object.keys(schema.tokens).map((t) => (
            <NodeChip key={t} tone="neutral" className="cursor-pointer" >
              <span onClick={() => setText(t)}>{t}</span>
            </NodeChip>
          ))}
          {text && <Notice tone="amber" className="ml-2 inline-block py-1">Token: {text}</Notice>}
        </div>
      )}
    </div>
  );
}

export function QueryBuilder({ schema, definition, onChange, contentType }: { schema: Schema; definition: QueryDefinition; onChange: (d: QueryDefinition) => void; contentType: string }) {
  const filters = definition.filters ?? { relation: 'AND', rules: [] };
  const sorts = definition.sort ?? [];

  const applies = (types: string[] | undefined) => !types?.length || types.includes('*') || types.includes(contentType);
  const scoped: Schema = {
    fields: schema.fields.filter((f) => applies(f.content_types)),
    relationships: schema.relationships.filter((r) => (r.source_type === '*' || applies(r.source_types))),
    taxonomies: schema.taxonomies.filter((t) => applies(t.content_types)),
    tokens: schema.tokens,
  };

  const setFilter = (g: QueryGroup) => onChange({ ...definition, filters: g });
  const updateSort = (i: number, s: { field?: string; path?: string; order: string }) => {
    const next = [...sorts];
    next[i] = s;
    onChange({ ...definition, sort: next });
  };
  const addSort = () => onChange({ ...definition, sort: [...sorts, { field: 'post.date', order: 'DESC' }] });
  const removeSort = (i: number) => onChange({ ...definition, sort: sorts.filter((_, j) => j !== i) });

  const sortableFields = scoped.fields.filter((f) => f.sortable);
  const sortOptions = sortableFields.length ? sortableFields : scoped.fields;

  return (
    <div className="space-y-5">
      <GroupEditor schema={scoped} group={filters} onChange={setFilter} />

      <div>
        <Label>Sort</Label>
        <div className="space-y-2">
          {sorts.map((s, i) => (
            <div key={i} className="flex items-center gap-2">
              <Select value={s.field ?? ''} onChange={(e) => updateSort(i, { field: e.target.value, order: s.order })} className="h-8 max-w-xs text-[12.5px]">
                {sortOptions.map((f) => (
                  <option key={f.id} value={f.id}>{f.label}</option>
                ))}
              </Select>
              <Select value={s.order} onChange={(e) => updateSort(i, { ...s, order: e.target.value })} className="h-8 w-28 text-[12.5px]">
                <option value="ASC">ASC</option>
                <option value="DESC">DESC</option>
              </Select>
              <Button variant="ghost" size="sm" icon={<Trash2 className="h-3.5 w-3.5" />} onClick={() => removeSort(i)} className="h-8 w-8 p-0 text-rust hover:bg-rust-soft" title="Remove sort" aria-label="Remove sort" />
            </div>
          ))}
          <Button variant="secondary" size="sm" icon={<Plus className="h-3.5 w-3.5" />} onClick={addSort}>Add sort</Button>
        </div>
      </div>
    </div>
  );
}
