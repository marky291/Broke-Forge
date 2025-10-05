# UI Components

This directory contains reusable UI components for BrokeForge. Components are built with React 19, TypeScript, and Tailwind CSS 4.0.

## Component Categories

### Layout Components

#### PageHeader

**Location:** `page-header.tsx`

**Purpose:** Provides consistent page header layout with title, description, and optional action button.

**When to Use:**
- At the top of every page to establish the page context
- When you need a consistent header layout across different pages
- When you want to add page-level actions (e.g., "Add Server" button)

**Example:**
```tsx
<PageHeader
  title="Commands"
  description="Execute ad-hoc commands on your server within the site's context."
  action={<Button>Add Command</Button>}
>
  <CardContainer>...</CardContainer>
</PageHeader>
```

---

#### CardContainer

**Location:** `card-container.tsx`

**Purpose:** Wraps content in a styled card with title, optional description, and optional action button.

**When to Use:**
- To group related content sections on a page
- When you need a titled section with consistent styling
- For forms, settings panels, or content blocks
- When you want section-level actions (e.g., "Edit", "Delete")

**Example:**
```tsx
<CardContainer
  title="Application"
  description="Repository information and deployment settings"
  action={<Button variant="ghost" size="sm">Edit</Button>}
>
  <div>Your content here</div>
</CardContainer>
```

---

### Form Components

#### CardInput

**Location:** `card-input.tsx`

**Purpose:** Horizontally-aligned label and text/number input for use within CardContainer components.

**When to Use:**
- Inside CardContainer for text or number inputs (e.g., memory limit, timeout values)
- When you need horizontal layout (label left, input right)
- For simple input fields with a clear label
- When you want consistent spacing and error handling

**Props:**
- `label`: Input label text
- `error`: Error message to display (optional)
- `id`: Custom ID (optional, auto-generated from label)
- `className`: Additional CSS classes (optional)
- All standard HTML input props (`value`, `onChange`, `type`, `placeholder`, etc.)

**Example:**
```tsx
<CardContainer title="PHP Settings">
  <div className="space-y-4">
    <CardInput
      label="Memory Limit"
      value={data.memory_limit}
      onChange={(e) => setData('memory_limit', e.target.value)}
      placeholder="256M"
      error={errors.memory_limit}
    />
    <CardInput
      label="Max Execution Time (seconds)"
      type="number"
      value={data.max_execution_time}
      onChange={(e) => setData('max_execution_time', parseInt(e.target.value))}
      placeholder="30"
      error={errors.max_execution_time}
    />
  </div>
</CardContainer>
```

---

#### CardInputDropdown

**Location:** `card-input-dropdown.tsx`

**Purpose:** Horizontally-aligned label and dropdown input for use within CardContainer components.

**When to Use:**
- Inside CardContainer for configuration options (e.g., PHP version, database type)
- When you need horizontal layout (label left, dropdown right)
- For simple select inputs with a clear label
- When you want consistent spacing and error handling

**Props:**
- `label`: Input label text
- `value`: Current selected value
- `onValueChange`: Callback when value changes
- `options`: Array of `{ value: string, label: string }` objects
- `placeholder`: Placeholder text (optional)
- `error`: Error message to display (optional)
- `id`: Custom ID (optional, auto-generated from label)
- `className`: Additional CSS classes (optional)

**Example:**
```tsx
<CardContainer title="Update PHP Version">
  <CardInputDropdown
    label="Version"
    value={data.version}
    onValueChange={(value) => setData('version', value)}
    options={[
      { value: '8.3', label: 'PHP 8.3' },
      { value: '8.2', label: 'PHP 8.2' },
    ]}
    placeholder="Select PHP version"
    error={errors.version}
  />
</CardContainer>
```

---

### Data Display Components

#### CardTable

**Location:** `card-table.tsx`

**Purpose:** Reusable table component designed for use within CardContainer components with a 12-column grid system.

**When to Use:**
- Displaying tabular data inside CardContainer components
- When you need a consistent table design across different pages
- For tables with custom row styling based on data state
- When you need flexible column sizing with the grid system

**Props:**
- `columns`: Array of column configurations (header, width, accessor/cell, align)
- `data`: Array of data items to display
- `emptyState`: React node to show when data is empty (optional)
- `rowClassName`: Function to generate custom row classes based on row data (optional)
- `getRowKey`: Function to generate unique keys for rows (optional)
- `pagination`: Pagination configuration object (optional)

**Column Configuration:**
- `header`: Column header label
- `width`: Column width in grid units (out of 12)
- `accessor`: Function to get cell value from row data
- `cell`: Custom cell renderer (use instead of accessor for complex content)
- `align`: Text alignment ('left', 'center', 'right')
- `headerClassName`: Custom header CSS classes
- `cellClassName`: Custom cell CSS classes

**Example (Basic):**
```tsx
<CardContainer title="Firewall Rules">
  <CardTable
    columns={[
      {
        header: 'Name',
        width: 3,
        accessor: (row) => <span className="text-sm">{row.name}</span>
      },
      {
        header: 'Port',
        width: 2,
        accessor: (row) => <span className="font-mono">{row.port}</span>
      },
      {
        header: 'Status',
        width: 2,
        cell: (row) => <Badge>{row.status}</Badge>
      },
      {
        header: 'Actions',
        width: 1,
        align: 'right',
        cell: (row, index) => (
          <Button onClick={() => handleDelete(row.id)}>Delete</Button>
        )
      },
    ]}
    data={rules}
    getRowKey={(rule) => rule.id}
    rowClassName={(rule) =>
      rule.status === 'pending' ? 'bg-gray-50' : 'hover:bg-muted/50'
    }
    emptyState={
      <div className="text-center py-8">No rules found</div>
    }
  />
</CardContainer>
```

**Example (With Pagination):**
```tsx
import { usePagination } from '@/components/ui/table-pagination';

const { paginatedData, paginationProps } = usePagination(metrics, 10);

<CardContainer title="Recent Metrics">
  <CardTable
    columns={metricsColumns}
    data={paginatedData}
    getRowKey={(metric) => metric.id}
    pagination={paginationProps}
  />
</CardContainer>
```

**Grid System:**
- Total of 12 columns available
- Column widths must add up to 12 for proper alignment
- Example: `[3, 2, 2, 2, 2, 1] = 12 columns`

---

#### TablePagination

**Location:** `table-pagination.tsx`

**Purpose:** Provides pagination controls for tables and lists with "Previous" and "Next" buttons.

**When to Use:**
- With large datasets that need to be split across multiple pages
- When displaying lists or tables with more than 10-20 items
- Use the `usePagination` hook for easy state management

**Components:**
- `TablePagination`: Pagination UI component
- `usePagination`: Custom hook for managing pagination state

**Example with Hook:**
```tsx
const { currentPage, setCurrentPage, paginatedData, paginationProps } = usePagination(data, 10);

return (
  <>
    <table>
      {paginatedData.map(item => <tr key={item.id}>...</tr>)}
    </table>
    <TablePagination {...paginationProps} />
  </>
);
```

**Example without Hook:**
```tsx
<TablePagination
  currentPage={currentPage}
  totalPages={Math.ceil(data.length / perPage)}
  totalItems={data.length}
  perPage={10}
  onPageChange={setCurrentPage}
/>
```

---

## Component Guidelines

### Creating New Reusable Components

When creating new reusable components:

1. **Add JSDoc comments** with description and example usage
2. **Define TypeScript interfaces** for all props
3. **Include sensible defaults** for optional props
4. **Support className prop** for custom styling when appropriate
5. **Update this README** with usage guidelines

### Naming Conventions

- **Layout components:** Describe their structural purpose (e.g., `PageHeader`, `CardContainer`)
- **Form components:** Prefix with context if specific (e.g., `CardInputDropdown`)
- **Generic components:** Use shadcn/ui naming (e.g., `Button`, `Input`, `Select`)

### When to Create a New Component

Create a new reusable component when:

- The pattern is used 3+ times across different pages
- The component encapsulates complex logic that should be shared
- You want to enforce consistent styling/behavior
- The component provides a common abstraction (e.g., form inputs, cards)

### When NOT to Create a Component

Avoid creating components when:

- Used only once or twice in the entire app
- The pattern is too specific to a single feature
- The abstraction makes the code harder to understand
- It's simpler to use existing primitives directly

---

## Base UI Components (shadcn/ui)

This directory also includes base UI primitives from shadcn/ui:

- `alert.tsx` - Alert notifications
- `avatar.tsx` - User avatars
- `badge.tsx` - Status badges
- `breadcrumb.tsx` - Breadcrumb navigation
- `button.tsx` - Button variants
- `card.tsx` - Base card component
- `checkbox.tsx` - Checkbox inputs
- `collapsible.tsx` - Collapsible sections
- `dialog.tsx` - Modal dialogs
- `dropdown-menu.tsx` - Dropdown menus
- `icon.tsx` - Icon wrapper
- `input.tsx` - Text inputs
- `label.tsx` - Form labels
- `navigation-menu.tsx` - Navigation menus
- `placeholder-pattern.tsx` - Loading placeholders
- `progress.tsx` - Progress bars
- `select.tsx` - Select dropdowns
- `separator.tsx` - Visual separators
- `sheet.tsx` - Side panels
- `sidebar.tsx` - Sidebar navigation
- `skeleton.tsx` - Loading skeletons
- `switch.tsx` - Toggle switches
- `textarea.tsx` - Multi-line text inputs
- `toggle.tsx` - Toggle buttons
- `toggle-group.tsx` - Toggle button groups
- `tooltip.tsx` - Tooltips

Refer to [shadcn/ui documentation](https://ui.shadcn.com) for usage of base components.
