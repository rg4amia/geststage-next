import { rankItem } from '@tanstack/match-sorter-utils';
import type {
  Column,
  Table as ReactTable,
  ColumnFiltersState,
  FilterFn} from '@tanstack/react-table';
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
import { Link } from '@/velzone/inertia-router';



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

      {!isServerPagination && (
      <Row className="align-items-center mt-2 g-3 text-center text-sm-start">
        <div className="col-sm">
          <div className="text-muted">Showing<span className="fw-semibold ms-1">{getState().pagination.pageSize}</span> of <span className="fw-semibold">{data.length}</span> Results
          </div>
        </div>
        <div className="col-sm-auto">
          <ul className="pagination pagination-separated pagination-md justify-content-center justify-content-sm-start mb-0">
            <li className={!getCanPreviousPage() ? "page-item disabled" : "page-item"}>
              <Link to="#" className="page-link" onClick={previousPage}>Previous</Link>
            </li>
            {getPageOptions().map((item: any, key: number) => (
              <React.Fragment key={key}>
                <li className="page-item">
                  <Link to="#" className={getState().pagination.pageIndex === item ? "page-link active" : "page-link"} onClick={() => setPageIndex(item)}>{item + 1}</Link>
                </li>
              </React.Fragment>
            ))}
            <li className={!getCanNextPage() ? "page-item disabled" : "page-item"}>
              <Link to="#" className="page-link" onClick={nextPage}>Next</Link>
            </li>
          </ul>
        </div>
      </Row>
      )}

      {isServerPagination && serverPagination && (
      <Row className="align-items-center mt-2 g-3 text-center text-sm-start">
        <div className="col-sm">
          <div className="text-muted">Affichage de <span className="fw-semibold ms-1">{data.length}</span> sur <span className="fw-semibold">{serverPagination.total}</span> résultats
          </div>
        </div>
        <div className="col-sm-auto">
          <ul className="pagination pagination-separated pagination-md justify-content-center justify-content-sm-start mb-0">
            {serverPagination.links?.map((link: any, key: number) => {
              const isActive = link.active;
              const isDisabled = link.url === null;
              
              let label = link.label;

              if (label.includes('Previous')) {
label = 'Précédent';
}

              if (label.includes('Next')) {
label = 'Suivant';
}

              return (
                <li key={key} className={`page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`}>
                  <button 
                    className="page-link" 
                    onClick={(e) => {
                      e.preventDefault();

                      if (!isDisabled && onPageChange && link.url) {
                        const urlParams = new URL(link.url, window.location.origin).searchParams;
                        const page = urlParams.get('page');

                        if (page) {
onPageChange(Number(page));
}
                      }
                    }}
                    dangerouslySetInnerHTML={{ __html: label }}
                  />
                </li>
              );
            })}
          </ul>
        </div>
      </Row>
      )}
    </Fragment>
  );
};

export default TableContainer;