import { rankItem } from '@tanstack/match-sorter-utils';
import type {
  Column,
  Table as ReactTable,
  ColumnFiltersState,
  FilterFn
} from '@tanstack/react-table';
import {
  useReactTable,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  flexRender
} from '@tanstack/react-table';
import React, { Fragment, useEffect, useState } from "react";
import { CardBody, Col, Row, Table } from "reactstrap";
import ServerPagination, { normalizePagination } from './ServerPagination';



// Column Filter
const Filter = ({
  column
}: {
  column: Column<any, unknown>;
  table: ReactTable<any>;
}) => {
  const columnFilterValue = column.getFilterValue();

  return (
    <>
      <DebouncedInput
        type="text"
        value={(columnFilterValue ?? '') as string}
        onChange={value => column.setFilterValue(value)}
        placeholder="Search..."
        className="w-36 border shadow rounded"
        list={column.id + 'list'}
      />
      <div className="h-1" />
    </>
  );
};

// Global Filter
const DebouncedInput = ({
  value: initialValue,
  onChange,
  debounce = 500,
  ...props
}: {
  value: string | number;
  onChange: (value: string | number) => void;
  debounce?: number;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'>) => {
  const [value, setValue] = useState(initialValue);

  useEffect(() => {
    setValue(initialValue);
  }, [initialValue]);

  useEffect(() => {
    const timeout = setTimeout(() => {
      onChange(value);
    }, debounce);

    return () => clearTimeout(timeout);
  }, [debounce, onChange, value]);

  return (
    <input {...props} value={value} id="search-bar-0" className="form-control border-0 search" onChange={e => setValue(e.target.value)} />
  );
};

interface TableContainerProps {
  columns?: any;
  data?: any;
  isGlobalFilter?: any;
  handleTaskClick?: any;
  customPageSize?: any;
  tableClass?: any;
  theadClass?: any;
  trClass?: any;
  thClass?: any;
  divClass?: any;
  SearchPlaceholder?: any;
  handleLeadClick?: any;
  handleCompanyClick?: any;
  handleContactClick?: any;
  handleTicketClick?: any;
  isServerPagination?: boolean;
  serverPagination?: any;
  onPageChange?: (page: number) => void;
}

const TableContainer = ({
  columns,
  data,
  isGlobalFilter,
  customPageSize,
  tableClass,
  theadClass,
  trClass,
  thClass,
  divClass,
  SearchPlaceholder,
  isServerPagination,
  serverPagination,
  onPageChange
}: TableContainerProps) => {
  const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
  const [globalFilter, setGlobalFilter] = useState('');

  const fuzzyFilter: FilterFn<any> = (row, columnId, value, addMeta) => {
    const itemRank = rankItem(row.getValue(columnId), value);
    addMeta({
      itemRank
    });

    return itemRank.passed;
  };

  const table = useReactTable({
    columns,
    data,
    filterFns: {
      fuzzy: fuzzyFilter,
    },
    state: {
      columnFilters,
      globalFilter,
    },
    onColumnFiltersChange: setColumnFilters,
    onGlobalFilterChange: setGlobalFilter,
    globalFilterFn: fuzzyFilter,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel()
  });

  const {
    getHeaderGroups,
    getRowModel,
    getCanPreviousPage,
    getCanNextPage,
    getPageOptions,
    setPageIndex,
    nextPage,
    previousPage,
    setPageSize,
    getState
  } = table;

  useEffect(() => {
    Number(customPageSize) && setPageSize(Number(customPageSize));
  }, [customPageSize, setPageSize]);

  return (
    <Fragment>
      {isGlobalFilter && <Row className="mb-3">
        <CardBody className="border border-dashed border-end-0 border-start-0">
          <form>
            <Row>
              <Col sm={5}>
                <div className="search-box me-2 mb-2 d-inline-block col-12">
                  <DebouncedInput
                    value={globalFilter ?? ''}
                    onChange={value => setGlobalFilter(String(value))}
                    placeholder={SearchPlaceholder}
                  />
                  <i className="bx bx-search-alt search-icon"></i>
                </div>
              </Col>
            </Row>
          </form>
        </CardBody>
      </Row>}


      <div className={divClass}>
        <Table hover className={tableClass}>
          <thead className={theadClass}>
            {getHeaderGroups().map((headerGroup: any) => (
              <tr className={trClass} key={headerGroup.id}>
                {headerGroup.headers.map((header: any) => (
                  <th key={header.id} className={thClass}  {...{
                    onClick: header.column.getToggleSortingHandler(),
                  }}>
                    {header.isPlaceholder ? null : (
                      <React.Fragment>
                        {flexRender(
                          header.column.columnDef.header,
                          header.getContext()
                        )}
                        {{
                          asc: ' ',
                          desc: ' ',
                        }
                        [header.column.getIsSorted() as string] ?? null}
                        {/* header.column.getCanFilter() ? (
                          <div>
                            <Filter column={header.column} table={table} />
                          </div>
                        ) : null */}
                      </React.Fragment>
                    )}
                  </th>
                ))}
              </tr>
            ))}
          </thead>

          <tbody>
            {getRowModel().rows.length > 0 ? (
              getRowModel().rows.map((row: any) => {
                return (
                  <tr key={row.id}>
                    {row.getVisibleCells().map((cell: any) => {
                      return (
                        <td key={cell.id}>
                          {flexRender(
                            cell.column.columnDef.cell,
                            cell.getContext()
                          )}
                        </td>
                      );
                    })}
                  </tr>
                );
              })
            ) : (
              <tr>
                <td colSpan={columns.length} className="text-center py-5 text-muted border-0">
                  <div className="avatar-sm mx-auto mb-4">
                    <div className="avatar-title bg-light text-primary rounded-circle fs-24">
                      <i className="ri-inbox-line"></i>
                    </div>
                  </div>
                  <h5 className="fs-14 fw-medium text-dark mb-1">Aucune donnée trouvée</h5>
                  <p className="text-muted mb-0 fs-13">La liste est actuellement vide ou aucun élément ne correspond à vos filtres.</p>
                </td>
              </tr>
            )}
          </tbody>
        </Table>
      </div>

      {!isServerPagination && (() => {
        const pageIndex = getState().pagination.pageIndex;
        const pageSize = getState().pagination.pageSize;
        const total = data.length;
        const lastPage = getPageOptions().length;
        const from = total === 0 ? 0 : pageIndex * pageSize + 1;
        const to = Math.min((pageIndex + 1) * pageSize, total);
        const fmt = (n: number) => n.toLocaleString('fr-FR');

        // Calcule les numéros de pages à afficher (avec ellipses)
        const buildPages = (): (number | '...')[] => {
          if (lastPage <= 1) {
return [];
}

          const delta = 1;
          const left = Math.max(2, pageIndex + 1 - delta);
          const right = Math.min(lastPage - 1, pageIndex + 1 + delta);
          const items: (number | '...')[] = [1];

          if (left > 2) {
items.push('...');
}

          for (let i = left; i <= right; i++) {
items.push(i);
}

          if (right < lastPage - 1) {
items.push('...');
}

          if (lastPage > 1) {
items.push(lastPage);
}

          return items;
        };

        const pages = buildPages();

        return (
          <div className="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 mt-3 pt-3 border-top">
            {/* Ligne d'information */}
            <div className="text-muted fs-13">
              Affichage de{' '}
              <span className="fw-semibold text-body">{fmt(from)}</span>
              {' '}à{' '}
              <span className="fw-semibold text-body">{fmt(to)}</span>
              {' '}sur{' '}
              <span className="fw-semibold text-body">{fmt(total)}</span>
              {' '}enregistrements
              {lastPage > 1 && (
                <>
                  <span className="mx-2 text-muted opacity-50">—</span>
                  Page{' '}
                  <span className="fw-semibold text-body">{fmt(pageIndex + 1)}</span>
                  {' '}sur{' '}
                  <span className="fw-semibold text-body">{fmt(lastPage)}</span>
                </>
              )}
            </div>

            {/* Boutons de navigation */}
            {lastPage > 1 && (
              <ul className="pagination pagination-separated mb-0 flex-shrink-0">
                <li className={`page-item${!getCanPreviousPage() ? ' disabled' : ''}`}>
                  <button
                    type="button"
                    className="page-link"
                    onClick={previousPage}
                    disabled={!getCanPreviousPage()}
                    aria-label="Page précédente"
                  >
                    <i className="ri-arrow-left-s-line align-middle" />
                  </button>
                </li>

                {pages.map((page, idx) =>
                  page === '...' ? (
                    <li key={`ellipsis-${idx}`} className="page-item disabled">
                      <span className="page-link px-2">…</span>
                    </li>
                  ) : (
                    <li
                      key={page}
                      className={`page-item${page === pageIndex + 1 ? ' active' : ''}`}
                    >
                      <button
                        type="button"
                        className="page-link"
                        onClick={() => setPageIndex((page as number) - 1)}
                        aria-current={page === pageIndex + 1 ? 'page' : undefined}
                        aria-label={`Page ${page}`}
                      >
                        {page}
                      </button>
                    </li>
                  )
                )}

                <li className={`page-item${!getCanNextPage() ? ' disabled' : ''}`}>
                  <button
                    type="button"
                    className="page-link"
                    onClick={nextPage}
                    disabled={!getCanNextPage()}
                    aria-label="Page suivante"
                  >
                    <i className="ri-arrow-right-s-line align-middle" />
                  </button>
                </li>
              </ul>
            )}
          </div>
        );
      })()}

      {isServerPagination && serverPagination && (
        <ServerPagination
          pagination={normalizePagination(serverPagination)}
          onPageChange={onPageChange}
          className="mt-2"
        />
      )}
    </Fragment>
  );
};

export default TableContainer;
