import { type ReactNode } from 'react';
import { ChevronLeft, ChevronRight, Inbox } from 'lucide-react';
import { cn } from '@/lib/utils';
import { EmptyState, SkeletonRows } from './ui';

export interface Column<T> {
  key: string;
  header: ReactNode;
  render: (row: T) => ReactNode;
  className?: string;
  align?: 'left' | 'right' | 'center';
}

interface DataTableProps<T> {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  loading?: boolean;
  emptyTitle?: string;
  emptyDesc?: string;
  emptyAction?: ReactNode;
  page?: number;
  pageCount?: number;
  onPageChange?: (page: number) => void;
  onRowClick?: (row: T) => void;
}

export function DataTable<T>({ columns, rows, rowKey, loading, emptyTitle = 'Nothing here', emptyDesc, emptyAction, page, pageCount, onPageChange, onRowClick }: DataTableProps<T>) {
  if (loading) {
    return (
      <div className="overflow-hidden rounded-lg border border-line bg-surface shadow-card">
        <SkeletonRows rows={5} />
      </div>
    );
  }

  if (!rows.length) {
    return <EmptyState icon={<Inbox className="h-5 w-5" />} title={emptyTitle} desc={emptyDesc} action={emptyAction} />;
  }

  return (
    <div className="overflow-hidden rounded-lg border border-line bg-surface shadow-card">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-line bg-surface-2/60">
              {columns.map((col) => (
                <th
                  key={col.key}
                  className={cn(
                    'whitespace-nowrap px-4 py-2.5 text-left font-mono text-[11px] font-medium uppercase tracking-wider text-ink-faint',
                    col.align === 'right' && 'text-right',
                    col.align === 'center' && 'text-center',
                    col.className,
                  )}
                >
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr
                key={rowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={cn('border-b border-line/70 transition-colors last:border-0', onRowClick && 'cursor-pointer hover:bg-indigo-soft/40')}
              >
                {columns.map((col) => (
                  <td key={col.key} className={cn('px-4 py-3 align-middle', col.align === 'right' && 'text-right', col.align === 'center' && 'text-center', col.className)}>
                    {col.render(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {pageCount !== undefined && pageCount > 1 && onPageChange && (
        <div className="flex items-center justify-between border-t border-line bg-surface-2/40 px-4 py-2.5">
          <span className="font-mono text-[11px] text-ink-faint">
            Page {page} / {pageCount}
          </span>
          <div className="flex gap-1">
            <button
              disabled={page! <= 1}
              onClick={() => onPageChange(page! - 1)}
              className="flex h-7 w-7 items-center justify-center rounded-md border border-line-strong bg-surface text-ink-soft transition hover:border-indigo hover:text-indigo-ink disabled:opacity-40"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <button
              disabled={page! >= pageCount}
              onClick={() => onPageChange(page! + 1)}
              className="flex h-7 w-7 items-center justify-center rounded-md border border-line-strong bg-surface text-ink-soft transition hover:border-indigo hover:text-indigo-ink disabled:opacity-40"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
