---
name: React Component Patterns
description: Guides development of React components using BrokeForge's established patterns for CardList, CardTable, CardContainer, status badges, metadata display, icons, menus, and layout conventions.
allowed-tools: Read, Write, Edit, Glob, Grep, mcp__laravel-boost__*
---

# React Component Patterns

## Context
You are building React components for BrokeForge, following established patterns for consistent UI/UX. This skill ensures components match existing conventions for layout, metadata display, status indicators, actions, and visual hierarchy.

## Core Components

### CardContainer
The foundational container component for sections with consistent styling.

**Usage:**
```tsx
import { CardContainer } from '@/components/ui/card-container';

<CardContainer
  title="Section Title"
  description="Optional description text"
  icon={<Icon />} // Optional icon (usually commented out in current implementation)
  action={<CardContainerAddButton label="Add Item" onClick={handleAdd} />}
  parentBorder={true} // Default: true. Set false when children have their own borders
>
  {/* Content here */}
</CardContainer>
```

**Key Props:**
- `title`: Section heading (required)
- `description`: Subtitle/description (optional)
- `icon`: ReactNode icon (optional, currently commented out in implementation)
- `action`: Action button/element displayed on the right (optional)
- `parentBorder`: Boolean - wraps children in border/padding if true (default: true)
- `className`: Additional CSS classes

**When to use parentBorder={false}:**
- When children have their own card/border styling (e.g., CardList, CardTable)
- When rendering multiple bordered items inside

### CardList
Reusable list component with consistent item rendering, actions dropdown, and empty states.

**Usage:**
```tsx
import { CardList, type CardListAction } from '@/components/card-list';

<CardList<DatabaseItem>
  title="Databases"
  description="Manage your database services"
  icon={<DatabaseIcon />}
  onAddClick={() => setShowModal(true)}
  addButtonLabel="Add Database" // Optional, icon-only if omitted
  items={databases}
  keyExtractor={(db) => db.id}
  renderItem={(db) => (
    <div className="flex items-center justify-between gap-3">
      {/* Left: Primary info */}
      <div className="min-w-0 flex-1">
        <div className="truncate text-sm font-medium">
          {db.name} {db.version}
        </div>
        <div className="truncate text-xs text-muted-foreground">
          Port {db.port} · {db.metadata}
        </div>
      </div>

      {/* Right: Status badge */}
      <div className="flex-shrink-0">
        <StatusBadge status={db.status} />
      </div>
    </div>
  )}
  actions={(db) => [
    {
      label: 'Edit',
      onClick: () => handleEdit(db),
      icon: <Pencil className="h-4 w-4" />,
      disabled: db.status === 'pending'
    },
    {
      label: 'Delete',
      onClick: () => handleDelete(db),
      variant: 'destructive',
      icon: <Trash2 className="h-4 w-4" />
    }
  ]}
  emptyStateMessage="No items yet"
  emptyStateIcon={<DatabaseIcon className="h-6 w-6 text-muted-foreground" />}
/>
```

**Key Props:**
- `title`: Section title (required)
- `description`: Section description (optional)
- `icon`: Icon for the section header (optional)
- `onAddClick`: Handler for add button - shows + button if provided (optional)
- `addButtonLabel`: Label for add button - icon-only if not provided (optional)
- `items`: Array of items to display (required)
- `renderItem`: Function to render each item (required)
- `keyExtractor`: Function to extract unique key from item (defaults to index)
- `onItemClick`: Makes items clickable (optional)
- `actions`: Array or function returning CardListAction[] for dropdown menu (optional)
- `emptyStateMessage`: Custom empty state text (optional)
- `emptyStateIcon`: Custom empty state icon (optional)
- `className`: Additional CSS classes (optional)

**CardListAction Interface:**
```tsx
interface CardListAction {
  label: string;              // Action label in dropdown
  onClick: (item: any) => void; // Handler receives the item
  variant?: 'default' | 'destructive'; // 'destructive' for delete/remove
  icon?: ReactNode;           // Optional icon before label
  disabled?: boolean;         // Whether action is disabled
}
```

**Layout Pattern for renderItem:**
```tsx
// Standard layout: Left content (flex-1), Right status (flex-shrink-0)
<div className="flex items-center justify-between gap-3">
  {/* Left: Primary info (expands to fill) */}
  <div className="min-w-0 flex-1">
    <div className="truncate text-sm font-medium">
      {/* Primary text */}
    </div>
    <div className="truncate text-xs text-muted-foreground">
      {/* Secondary metadata */}
    </div>
  </div>

  {/* Right: Status indicator (fixed width) */}
  <div className="flex-shrink-0">
    <StatusBadge status={item.status} />
  </div>
</div>
```

### CardTable
Table component for tabular data with flexbox column layout.

**Usage:**
```tsx
import { CardTable, type CardTableColumn } from '@/components/ui/card-table';

const columns: CardTableColumn<Rule>[] = [
  {
    header: 'Name',
    accessor: (row) => row.name,
    align: 'left'
  },
  {
    header: 'Port',
    accessor: (row) => row.port,
    align: 'center'
  },
  {
    header: 'Status',
    cell: (row) => <Badge>{row.status}</Badge>,
    align: 'right'
  }
];

<CardContainer title="Firewall Rules">
  <CardTable
    columns={columns}
    data={rules}
    emptyState={<EmptyState />}
    getRowKey={(row) => row.id}
    rowClassName={(row) => row.isHighlighted ? 'bg-blue-50' : ''}
    pagination={{
      currentPage: 1,
      totalPages: 10,
      totalItems: 100,
      perPage: 10,
      onPageChange: (page) => handlePageChange(page)
    }}
  />
</CardContainer>
```

**Key Props:**
- `columns`: Array of column configurations (required)
- `data`: Table data array (required)
- `emptyState`: React node for empty state (optional)
- `rowClassName`: Function to compute row classes based on row data (optional)
- `getRowKey`: Function to extract unique key (defaults to index)
- `pagination`: Pagination configuration (optional)

**CardTableColumn Interface:**
```tsx
interface CardTableColumn<T> {
  header: string;                      // Column header text
  accessor?: (row: T) => ReactNode;    // Simple accessor function
  cell?: (row: T, index: number) => ReactNode; // Custom cell renderer
  align?: 'left' | 'center' | 'right'; // Text alignment
  headerClassName?: string;            // Custom header classes
  cellClassName?: string;              // Custom cell classes
}
```

## Status Badges

### Standard Status Badge Pattern
Use inline badge spans for status indicators with consistent color scheme:

```tsx
{/* Pending */}
{item.status === 'pending' && (
  <span className="inline-flex items-center gap-1 rounded bg-slate-500/10 px-1.5 py-0.5 text-xs text-slate-600 dark:text-slate-400">
    <Loader2 className="h-3 w-3 animate-spin" />
    Pending
  </span>
)}

{/* Installing/Processing */}
{item.status === 'installing' && (
  <span className="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 text-xs text-blue-600 dark:text-blue-400">
    <Loader2 className="h-3 w-3 animate-spin" />
    Installing
  </span>
)}

{/* Active/Success */}
{item.status === 'active' && (
  <span className="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-600 dark:text-emerald-400">
    <CheckCircle className="h-3 w-3" />
    Active
  </span>
)}

{/* Failed/Error */}
{item.status === 'failed' && (
  <span className="inline-flex items-center gap-1 rounded bg-red-500/10 px-1.5 py-0.5 text-xs text-red-600 dark:text-red-400">
    <AlertCircle className="h-3 w-3" />
    Failed
  </span>
)}

{/* Updating */}
{item.status === 'updating' && (
  <span className="inline-flex items-center gap-1 rounded bg-blue-500/10 px-1.5 py-0.5 text-xs text-blue-600 dark:text-blue-400">
    <Loader2 className="h-3 w-3 animate-spin" />
    Updating
  </span>
)}

{/* Uninstalling/Removing */}
{item.status === 'uninstalling' && (
  <span className="inline-flex items-center gap-1 rounded bg-orange-500/10 px-1.5 py-0.5 text-xs text-orange-600 dark:text-orange-400">
    <Loader2 className="h-3 w-3 animate-spin" />
    Uninstalling
  </span>
)}

{/* Stopped/Inactive */}
{item.status === 'stopped' && (
  <span className="inline-flex items-center gap-1 rounded bg-gray-500/10 px-1.5 py-0.5 text-xs text-gray-600 dark:text-gray-400">
    Stopped
  </span>
)}

{/* Warning/Inactive */}
{item.status === 'inactive' && (
  <span className="inline-flex items-center gap-1 rounded bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-600 dark:text-amber-400">
    <Pause className="h-3 w-3" />
    Inactive
  </span>
)}
```

### Status Color Scheme
- **Pending**: `slate-500/10` background, `slate-600` text (dark: `slate-400`)
- **Installing/Processing/Updating**: `blue-500/10` background, `blue-600` text (dark: `blue-400`)
- **Active/Success**: `emerald-500/10` background, `emerald-600` text (dark: `emerald-400`)
- **Failed/Error**: `red-500/10` background, `red-600` text (dark: `red-400`)
- **Uninstalling/Removing**: `orange-500/10` background, `orange-600` text (dark: `orange-400`)
- **Stopped**: `gray-500/10` background, `gray-600` text (dark: `gray-400`)
- **Inactive/Warning**: `amber-500/10` background, `amber-600` text (dark: `amber-400`)

### Status Badge Classes Pattern
```tsx
className="inline-flex items-center gap-1 rounded bg-{color}-500/10 px-1.5 py-0.5 text-xs text-{color}-600 dark:text-{color}-400"
```

### Status Icons
- **Loading states**: `<Loader2 className="h-3 w-3 animate-spin" />`
- **Success**: `<CheckCircle className="h-3 w-3" />`
- **Error**: `<AlertCircle className="h-3 w-3" />`
- **Warning**: `<AlertCircle className="h-3 w-3" />`
- **Inactive**: `<Pause className="h-3 w-3" />`

### Badge Component (UI Library)
For simpler use cases, use the Badge component from `@/components/ui/badge`:

```tsx
import { Badge } from '@/components/ui/badge';

<Badge variant="default">Active</Badge>
<Badge variant="secondary">Pending</Badge>
<Badge variant="destructive">Failed</Badge>
<Badge variant="outline">Custom</Badge>

// Custom colors
<Badge className="border-green-200 bg-green-100 text-green-800">
  Allow
</Badge>
```

## Metadata Display Patterns

### Primary + Secondary Info
Always structure item info as primary (medium weight) + secondary (muted):

```tsx
<div className="min-w-0 flex-1">
  {/* Primary: Name, title, or main identifier */}
  <div className="truncate text-sm font-medium">
    {item.name} {item.version}
  </div>

  {/* Secondary: Metadata with middle dot separator */}
  <div className="truncate text-xs text-muted-foreground">
    Port {item.port} · {item.detail}
  </div>
</div>
```

### Multi-line Metadata
For more complex metadata, use multiple lines:

```tsx
<div className="min-w-0 flex-1">
  {/* Primary */}
  <div className="flex items-center gap-2">
    <h4 className="truncate text-sm font-medium">{task.name}</h4>
    <StatusBadge status={task.status} />
  </div>

  {/* Secondary: Command */}
  <p className="mt-1 truncate font-mono text-xs text-muted-foreground">
    {task.command}
  </p>

  {/* Tertiary: Additional metadata with dots */}
  <div className="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
    <span>{task.working_directory}</span>
    <span>•</span>
    <span>{task.processes} {task.processes === 1 ? 'process' : 'processes'}</span>
    <span>•</span>
    <span>User: {task.user}</span>
  </div>
</div>
```

### Text Styling Conventions
- **Primary text**: `text-sm font-medium` or `text-sm font-medium text-foreground`
- **Secondary text**: `text-xs text-muted-foreground`
- **Commands/code**: Add `font-mono` - `font-mono text-xs text-muted-foreground`
- **Truncation**: Use `truncate` class for text that might overflow
- **Separators**: Use middle dot `·` or bullet `•` between metadata items

## Icons

### Lucide React Icons
Import icons from `lucide-react`:

```tsx
import {
  AlertCircle,
  CheckCircle,
  Clock,
  Database,
  Eye,
  Loader2,
  MoreVertical,
  Pause,
  Pencil,
  Play,
  RefreshCw,
  RotateCw,
  Shield,
  Trash2
} from 'lucide-react';
```

### Icon Sizing
- **Small icons** (badges, inline): `h-3 w-3` or `h-4 w-4`
- **Medium icons** (buttons, decorative): `h-5 w-5`
- **Large icons** (empty states, headers): `h-6 w-6` or `h-12 w-12`
- **Loading spinners**: Always use `Loader2` with `animate-spin`

### Custom SVG Icons
For custom icons, follow this pattern:

```tsx
<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="6" cy="6" r="5" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
  <path d="M6 3v3l2 1" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" />
</svg>
```

## Actions & Menus

### Dropdown Menu (Three-dot Menu)
CardList automatically includes dropdown menu with MoreVertical icon. Actions are defined in the `actions` prop:

```tsx
actions={(item) => {
  const actions: CardListAction[] = [];
  const isInTransition = item.status === 'pending' || item.status === 'installing';

  // Conditional actions based on status
  if (item.status === 'active') {
    actions.push({
      label: 'Edit',
      onClick: () => handleEdit(item),
      icon: <Pencil className="h-4 w-4" />,
      disabled: isInTransition
    });
  }

  if (item.status === 'failed') {
    actions.push({
      label: 'Retry',
      onClick: () => handleRetry(item),
      icon: <RotateCw className="h-4 w-4" />,
      disabled: processing
    });
  }

  // Destructive action always last
  actions.push({
    label: 'Delete',
    onClick: () => handleDelete(item),
    variant: 'destructive',
    icon: <Trash2 className="h-4 w-4" />,
    disabled: isInTransition
  });

  return actions;
}}
```

### Manual Dropdown Menu
For custom implementations outside CardList:

```tsx
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { MoreVertical } from 'lucide-react';

<DropdownMenu>
  <DropdownMenuTrigger asChild>
    <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
      <MoreVertical className="h-4 w-4" />
    </Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent align="end">
    <DropdownMenuItem onClick={() => handleEdit(item)}>
      <Pencil className="mr-2 h-4 w-4" />
      Edit
    </DropdownMenuItem>
    <DropdownMenuItem
      onClick={() => handleDelete(item)}
      className="text-red-600"
    >
      <Trash2 className="mr-2 h-4 w-4" />
      Delete
    </DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>
```

## Empty States

### CardList Empty State
CardList handles empty states automatically:

```tsx
<CardList
  items={items}
  emptyStateMessage="No databases installed on this server yet."
  emptyStateIcon={<DatabaseIcon className="h-6 w-6 text-muted-foreground" />}
  // ... other props
/>
```

### Custom Empty State Component
For manual empty states:

```tsx
<div className="flex flex-col items-center justify-center px-6 py-8 text-center">
  <div className="mb-3 rounded-full bg-muted p-3">
    <DatabaseIcon className="h-6 w-6 text-muted-foreground" />
  </div>
  <p className="text-sm text-muted-foreground">No items yet</p>
  <p className="mt-1 text-xs text-muted-foreground">
    Additional descriptive text here
  </p>
</div>
```

### InstallSkeleton Pattern
For features requiring installation:

```tsx
import { InstallSkeleton } from '@/components/install-skeleton';

<CardContainer title="Supervisor">
  <InstallSkeleton
    icon={Eye}
    title="Supervisor Not Installed"
    description="Install Supervisor to manage long-running processes."
    buttonLabel="Install Supervisor"
    onInstall={handleInstall}
    isInstalling={processing}
  />
</CardContainer>
```

## Modals & Dialogs

### CardFormModal
Reusable modal for forms:

```tsx
import { CardFormModal } from '@/components/ui/card-form-modal';

<CardFormModal
  open={isOpen}
  onOpenChange={setIsOpen}
  title="Create Task"
  description="Configure your scheduled task"
  onSubmit={handleSubmit}
  submitLabel="Create Task"
  isSubmitting={processing}
  submittingLabel="Creating..."
>
  <div className="space-y-4">
    <div className="space-y-2">
      <Label htmlFor="name">Name</Label>
      <Input
        id="name"
        value={data.name}
        onChange={(e) => setData('name', e.target.value)}
        placeholder="Enter name"
        required
      />
      {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
    </div>
    {/* More fields */}
  </div>
</CardFormModal>
```

### Dialog Component
For more complex dialogs:

```tsx
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

<Dialog open={isOpen} onOpenChange={setIsOpen}>
  <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
    <DialogHeader>
      <DialogTitle>Dialog Title</DialogTitle>
      <DialogDescription>Dialog description</DialogDescription>
    </DialogHeader>
    {/* Content */}
  </DialogContent>
</Dialog>
```

## Real-time Updates (Reverb)

### WebSocket Pattern
Use `useEcho` from `@laravel/echo-react` + `router.reload()`:

```tsx
import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

// Listen for server updates
useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
  router.reload({
    only: ['server'], // Only reload server prop
    preserveScroll: true,
    preserveState: true,
  });
});
```

### Why This Pattern
- Model events automatically broadcast changes via Reverb
- Frontend listens for WebSocket events
- When event received, fetch fresh data via Inertia
- No manual event dispatching needed
- No polling required

## Page Structure

### Standard Page Layout
```tsx
import ServerLayout from '@/layouts/server/layout';
import { PageHeader } from '@/components/ui/page-header';
import { Head } from '@inertiajs/react';

export default function PageName({ server }: { server: Server }) {
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard.url() },
    { title: server.vanity_name, href: showServer({ server: server.id }).url },
    { title: 'Page Name', href: '#' },
  ];

  return (
    <ServerLayout server={server} breadcrumbs={breadcrumbs}>
      <Head title={`${server.vanity_name} - Page Name`} />

      <div className="space-y-6">
        <PageHeader
          title="Page Title"
          description="Page description"
          icon={IconComponent}
        />

        <CardList {...props} />
        {/* More components */}
      </div>
    </ServerLayout>
  );
}
```

## Spacing & Layout

### Vertical Spacing
- Use `space-y-6` for major page sections
- Use `space-y-4` for form fields and related items
- Use `space-y-2` for tightly related items (label + input)
- Use `gap-3` for horizontal spacing in flex containers
- Use `mt-1` for secondary text below primary text
- Use `mt-1.5` for tertiary metadata

### Padding
- CardContainer with border: `px-6 py-6`
- CardList items: `px-6 py-5`
- CardTable cells: `px-4 py-3`
- Empty states: `px-6 py-8`

### Borders & Dividers
- Use `divide-y` for vertical dividers between items
- Border color: `border-neutral-200 dark:border-white/8`
- Divider color: `divide-neutral-200 dark:divide-white/8`
- Rounded corners: `rounded-xl` for containers, `rounded` or `rounded-md` for badges

## Responsive Design

### Flex Patterns
- Use `flex items-center justify-between` for horizontal layouts
- Use `flex-1` for expanding content
- Use `flex-shrink-0` for fixed-width items (badges, buttons)
- Use `min-w-0` with `flex-1` to enable text truncation

### Grid Patterns
- Form grids: `grid grid-cols-1 gap-4 md:grid-cols-2`
- Full-width fields: `md:col-span-2`

## Forms

### Inertia Form Pattern
```tsx
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing, errors, reset } = useForm({
  name: '',
  port: 3306,
});

const handleSubmit = (e: React.FormEvent) => {
  e.preventDefault();
  post(`/servers/${server.id}/resource`, {
    preserveScroll: true,
    onSuccess: () => {
      reset();
      setDialogOpen(false);
    },
  });
};
```

### Form Field Pattern
```tsx
<div className="space-y-2">
  <Label htmlFor="field_name">Field Label</Label>
  <Input
    id="field_name"
    value={data.field_name}
    onChange={(e) => setData('field_name', e.target.value)}
    placeholder="Placeholder text"
    required
    disabled={processing}
  />
  <p className="text-xs text-muted-foreground">Helper text</p>
  {errors.field_name && (
    <p className="text-sm text-red-600">{errors.field_name}</p>
  )}
</div>
```

## Dark Mode

All components must support dark mode using Tailwind's `dark:` variant:

```tsx
// Background
className="bg-white dark:bg-[#141514]"

// Text
className="text-foreground"
className="text-muted-foreground"

// Borders
className="border-neutral-200 dark:border-white/8"
className="divide-neutral-200 dark:divide-white/8"

// Status badges automatically support dark mode with color scheme
```

## Common Mistakes to Avoid

### ❌ Don't Do This
```tsx
// Don't use margins for spacing list items
<div className="mb-4">Item</div>

// Don't forget flex-shrink-0 on badges
<Badge>{status}</Badge>

// Don't use arbitrary status colors
<span className="text-green-500">Active</span>

// Don't create custom empty states when component supports it
if (items.length === 0) return <CustomEmpty />;
```

### ✅ Do This
```tsx
// Use gap utilities for spacing
<div className="flex gap-3">

// Always use flex-shrink-0 on badges
<div className="flex-shrink-0">
  <StatusBadge status={item.status} />
</div>

// Use standard status badge pattern
{item.status === 'active' && (
  <span className="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-600 dark:text-emerald-400">
    <CheckCircle className="h-3 w-3" />
    Active
  </span>
)}

// Use component's built-in empty state
<CardList
  items={items}
  emptyStateMessage="No items yet"
  emptyStateIcon={<Icon />}
/>
```

## Checklist for New Components

When creating a new component displaying list data:

- [ ] Use CardContainer for section wrapper
- [ ] Use CardList for list layouts (or CardTable for tabular data)
- [ ] Implement standard status badges with correct colors
- [ ] Structure metadata as primary + secondary with proper text styles
- [ ] Use flex with flex-1 and flex-shrink-0 for responsive layout
- [ ] Add icon to CardList/CardContainer title
- [ ] Include empty state message and icon
- [ ] Add dropdown actions if items have actions
- [ ] Support dark mode with dark: variants
- [ ] Use gap utilities for spacing (not margins)
- [ ] Test text truncation with long content
- [ ] Add real-time updates via useEcho if applicable
- [ ] Match spacing conventions (space-y-6, px-6 py-5, etc.)
- [ ] Use correct icon sizes (h-3 w-3 for badges, h-4 w-4 for actions)
- [ ] Add loading states with Loader2 and animate-spin

## Quick Reference

### Component Imports
```tsx
// Layout components
import { CardContainer } from '@/components/ui/card-container';
import { CardList, type CardListAction } from '@/components/card-list';
import { CardTable, type CardTableColumn } from '@/components/ui/card-table';
import { PageHeader } from '@/components/ui/page-header';

// UI components
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

// Dialogs
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CardFormModal } from '@/components/ui/card-form-modal';

// Dropdown
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

// Icons
import { Loader2, CheckCircle, AlertCircle, MoreVertical, Trash2, Pencil } from 'lucide-react';

// Inertia
import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
```

### Common Patterns Reference
```tsx
// Status badge
<span className="inline-flex items-center gap-1 rounded bg-{color}-500/10 px-1.5 py-0.5 text-xs text-{color}-600 dark:text-{color}-400">
  <Icon className="h-3 w-3" />
  Status Text
</span>

// Item layout
<div className="flex items-center justify-between gap-3">
  <div className="min-w-0 flex-1">
    <div className="truncate text-sm font-medium">{primary}</div>
    <div className="truncate text-xs text-muted-foreground">{secondary}</div>
  </div>
  <div className="flex-shrink-0">
    <StatusBadge />
  </div>
</div>

// Form field
<div className="space-y-2">
  <Label htmlFor="field">Label</Label>
  <Input id="field" value={data.field} onChange={(e) => setData('field', e.target.value)} />
  {errors.field && <p className="text-sm text-red-600">{errors.field}</p>}
</div>

// Real-time updates
useEcho(`servers.${server.id}`, 'ServerUpdated', () => {
  router.reload({ only: ['server'], preserveScroll: true, preserveState: true });
});
```