# NexusDesk — Wireframes & Design System (Stage 4)

Defines the **visual design system** (tokens, components, motion, accessibility) and **low-fidelity
wireframes** for every key screen. A rendered, interactive visual of this system accompanies this
document as an Artifact (see §6). The design system is implemented once as a CSS token layer over
Bootstrap 5.3 so re-branding is a settings change, not a rewrite.

**Design intent:** software that reads as *2026*. Calm, spacious, confident. Soft elevation and
subtle depth, restrained gradients/glass on hero surfaces only, semantic colour for state, and
data-first information design. No stock admin-template look.

---

## 1. Design tokens

### 1.1 Colour

Neutrals carry a slight cool/indigo bias so they read as chosen, not default grey. Brand is a single
confident indigo; a cyan accent is reserved for data/charts so it never competes with primary
actions. Semantic colours match the seeded ticket-status palette.

| Token | Light | Dark | Use |
|---|---|---|---|
| `--ground` | `#f6f7fb` | `#0b0f1a` | App background |
| `--surface` | `#ffffff` | `#131826` | Cards, panels |
| `--surface-2` | `#f9fafc` | `#1a2030` | Nested/subtle surfaces |
| `--ink` | `#0f172a` | `#e8ecf4` | Primary text |
| `--muted` | `#64748b` | `#94a3b8` | Secondary text |
| `--border` | `#e6e9f2` | `#242c3d` | Hairlines, dividers |
| `--brand` | `#4f46e5` | `#7c76f2` | Primary actions, active nav |
| `--brand-soft` | `#eef2ff` | `#1e1b4b` | Brand tints, selected rows |
| `--accent` | `#0891b2` | `#22d3ee` | Charts, data highlights |
| `--success` | `#16a34a` | `#4ade80` | Resolved, healthy SLA |
| `--warning` | `#d97706` | `#fbbf24` | At-risk, pending |
| `--danger` | `#dc2626` | `#f87171` | Breach, errors, destructive |
| `--info` | `#2563eb` | `#60a5fa` | Neutral info states |

Brand + accent are the **only** two identity hues; everything else is neutral or semantic. All pairs
meet WCAG-AA contrast on their intended ground in both themes.

### 1.2 Typography

- **UI/Display:** system-ui stack (`-apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial`) —
  crisp, native, zero web-font latency (important behind restrictive networks / offline demos).
  Headings use tighter tracking (`-0.02em`) and `text-wrap: balance`.
- **Numeric/reference:** `ui-monospace, "SF Mono", "Cascadia Code", Menlo` with
  `font-variant-numeric: tabular-nums` for ticket references, IDs, money, and table figures.
- **Type scale** (1.25 ratio): `12 · 13 · 14(base) · 16 · 20 · 25 · 31 · 39 px`. Body line-height
  1.6; headings 1.2. Uppercase labels get `0.06em` letter-spacing.

### 1.3 Spacing, radius, elevation, motion

| Token | Value | |
|---|---|---|
| Spacing scale | `4 · 8 · 12 · 16 · 24 · 32 · 48 · 64` | 8px base grid |
| Radius | `--r-sm 8` · `--r 12` · `--r-lg 16` · `--r-pill 999` | cards use `--r-lg` |
| Shadow-1 | `0 1px 2px rgba(15,23,42,.06)` | resting cards |
| Shadow-2 | `0 8px 24px -6px rgba(15,23,42,.12)` | popovers, hovers |
| Glass | `backdrop-filter: blur(12px)` + translucent surface | topbar, auth hero only |
| Motion | 150ms ease (micro), 240ms ease-out (panels) | disabled under `prefers-reduced-motion` |

---

## 2. Component library

Built as reusable view partials (Stage 6+) so nothing is hand-rolled per page.

- **Buttons:** primary (brand), secondary (surface + border), ghost, danger; sizes sm/md; loading
  state with spinner; icon buttons.
- **Forms:** floating/label-top fields, help text, inline validation (red border + message), selects,
  multiselect chips, file dropzone with progress, toggle switches, segmented controls.
- **Cards & panels:** base card (radius-lg, shadow-1), stat tile (label · big number · delta ·
  sparkline), section panel with header actions.
- **Data table:** sticky header, sortable columns, row hover, row selection + bulk action bar,
  pagination, empty state, mobile → stacked cards.
- **Badges & chips:** status pill (semantic dot + label), priority chip (colour-coded), tag chip,
  count badge.
- **SLA meter:** horizontal progress with threshold colouring (green→amber→red) + remaining-time label.
- **Timeline / conversation:** alternating message bubbles (customer vs agent), internal-note style
  (amber, "internal" label), system events (muted, inline), attachment thumbnails.
- **Navigation:** collapsible sidebar (icon+label, active state, section groups), topbar (search,
  notifications dropdown, theme toggle, profile menu), breadcrumbs, tabs.
- **Overlays:** modal (confirm/destructive variants), slide-over drawer (filters, quick-view),
  toast notifications (stacked, auto-dismiss, semantic), popover.
- **Charts (Chart.js):** line (volume/trend), bar (dept comparison), doughnut (status/CSAT mix),
  horizontal bar (agent load) — themed from tokens, muted gridlines, emphasized endpoints.

---

## 3. Key wireframes (low-fi)

### 3.1 Agent desk dashboard `/desk`

```
┌ TOPBAR ─────────────────────────────────────────────────────────────────────┐
│ ≡  NexusDesk        ⌕ Search tickets, people, invoices…      🔔3  🌗  ▾ Alex  │
├───────────┬──────────────────────────────────────────────────────────────────┤
│ ◑ Dash    │  Good morning, Alex                              [+ New ticket]    │
│ ▤ Queue   │  ┌ Open ─────┐ ┌ Assigned─┐ ┌ SLA risk ┐ ┌ Resolved today ┐        │
│ ⌂ Companies│ │   42      │ │   12     │ │   3 ⚠    │ │     8   ▲        │        │
│ ◔ Contacts│  │  ▁▂▄▆█ +6%│ │          │ │  amber   │ │   sparkline     │        │
│ ▢ KB      │  └──────────┘ └──────────┘ └──────────┘ └────────────────┘        │
│ ✎ Canned  │  ┌ My queue ───────────────────────────────┐ ┌ SLA at-risk ────┐  │
│           │  │ ● NEXUS-1042 · Login broken  High  1h ●  │ │ NEXUS-1039 12m ⚠│  │
│           │  │ ● NEXUS-1041 · Invoice query Med   3h    │ │ NEXUS-1044 41m  │  │
│  ── ── ── │  │ ● NEXUS-1038 · SSL renewal   Low   1d    │ │                 │  │
│ 🔔 Notif   │  │ … sortable, click → workspace            │ └─────────────────┘  │
│ ▾ Profile │  └──────────────────────────────────────────┘                      │
└───────────┴──────────────────────────────────────────────────────────────────┘
```

### 3.2 Ticket workspace `/desk/tickets/{id}` — the core screen

```
┌───────────┬─────────────────────────────────────────┬───────────────────────┐
│ SIDEBAR   │ ‹ Queue   NEXUS-1042 · Login broken      │  REQUESTER            │
│           │ [In Progress ▾] [High ▾]  ⋯ actions      │  Jane Cole · Acme Ltd │
│           ├─────────────────────────────────────────┤  jane@acme.com        │
│           │  ┌ Jane (customer) · 10:02 ────────────┐ │  ┌ Finance ─────────┐ │
│           │  │ I can't log in since this morning…  │ │  │ Balance $1,240 ⚠ │ │
│           │  └─────────────────────────────────────┘ │  │ 2 open invoices  │ │
│           │      ┌ Alex (agent) · 10:14 ───────────┐ │  └──────────────────┘ │
│           │      │ Thanks Jane — can you confirm…  │ │  STATUS   In Progress │
│           │      └─────────────────────────────────┘ │  PRIORITY High        │
│           │  ┌ 🟡 Internal note · Alex ────────────┐ │  ASSIGNEE Alex ▾      │
│           │  │ Reset her 2FA if this recurs.       │ │  DEPT     Support ▾   │
│           │  └─────────────────────────────────────┘ │  TAGS  auth  urgent + │
│           │  ┌ Reply ─────────────────────────────┐  │  ┌ SLA ─────────────┐ │
│           │  │ [B I U • link] [canned▾] [🤖 AI ▾] │  │  │ Response ✓ met    │ │
│           │  │ …type reply…               [📎]    │  │  │ Resolve ▓▓▓░ 1h12m│ │
│           │  │ ☐ internal note   [Send ▸] [Resolve]│  │  └──────────────────┘ │
│           │  └────────────────────────────────────┘  │  ⏱ Log time  · Watchers│
└───────────┴─────────────────────────────────────────┴───────────────────────┘
   AI ▾ menu: Summarise · Suggest reply · Sentiment · Rewrite · Translate · KB suggest
```

### 3.3 Customer portal dashboard `/portal`

```
┌───────────┬──────────────────────────────────────────────────────────────────┐
│ Dashboard │  Welcome back, Jane            Acme Ltd workspace                  │
│ Tickets   │  ┌ Open tickets ┐ ┌ Invoices due ┐ ┌ Renewals (30d) ┐             │
│ Knowledge │  │     2        │ │  $1,240  ⚠   │ │      1 domain   │             │
│ Invoices  │  └──────────────┘ └──────────────┘ └─────────────────┘             │
│ Quotes    │  ┌ Recent tickets ───────────────┐ ┌ Quick actions ───────────┐   │
│ Projects  │  │ NEXUS-1042 Login   In progress│ │ [+ New ticket]            │   │
│ Domains   │  │ NEXUS-1030 Email   Resolved ★ │ │ [Browse knowledge base]  │   │
│ Hosting   │  └───────────────────────────────┘ │ [View invoices]          │   │
│ SSL       │  ┌ Your services ────────────────┐ └──────────────────────────┘   │
│ Documents │  │ 🌐 acme.com    expires 20d ⚠  │                                │
│ Profile   │  │ ⚙ Hosting Pro  renews 3mo     │  ┌ Notifications ───────────┐  │
│           │  │ 🔒 SSL valid   90d            │  │ • Invoice INV-0231 issued│  │
│           │  └───────────────────────────────┘  └──────────────────────────┘  │
└───────────┴──────────────────────────────────────────────────────────────────┘
```

### 3.4 Submit ticket (guest) `/submit`  ·  3.5 Manager reports `/manage/reports/sla`

```
 SUBMIT TICKET                                MANAGER · SLA REPORT
 ┌──────────────────────────────┐            ┌ Filters: Dept ▾  Last 30d ▾  [Export ▾]┐
 │ Subject  [__________________]│            │ ┌ Compliance ┐ ┌ Breaches ┐ ┌ At-risk ┐│
 │ Dept     [Support        ▾] │            │ │  94.2% ▲   │ │   7      │ │   3 ⚠   ││
 │ Priority [Medium         ▾] │            │ └────────────┘ └──────────┘ └─────────┘│
 │ Message  [                 ]│            │ ┌ SLA compliance trend (line) ────────┐│
 │          [                 ]│            │ │      ╱╲    ╱╲___╱                    ││
 │ 📎 Drop files or browse      │            │ │ ____╱  ╲__╱                          ││
 │ [ Submit ticket ▸ ]          │            │ └─────────────────────────────────────┘│
 └──────────────────────────────┘            │ Breached tickets table · export PDF/CSV │
   → success page shows NEXUS-#              └─────────────────────────────────────────┘
```

### 3.6 Admin settings shell `/admin/settings/*`

```
┌ Settings ──────────────────────────────────────────────────────────────────┐
│ [General][Branding][Mail][Templates][Security][Zoho][AI][API][Modules]      │
│ ── Branding ───────────────────────────────────────────────────────────────│
│  App name  [NexusDesk]      Logo [⬆ upload]   Preview: ◑ NexusDesk           │
│  Primary   [#4f46e5]■  Accent [#0891b2]■   Default theme [System ▾]          │
│  [ Save changes ]   → live re-theme, no code change                          │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Responsive behaviour

- **≥1200px:** full sidebar + right rail (ticket workspace three-column).
- **768–1199px:** sidebar collapses to icons; ticket right-rail becomes a top summary bar.
- **<768px:** off-canvas sidebar drawer; tables → stacked cards; ticket rail → accordion;
  reply box docks to bottom. Touch targets ≥44px.

## 5. Accessibility & motion

- Semantic landmarks (`header/nav/main/aside`), skip-link, logical heading order.
- Visible keyboard focus rings (brand, 2px); full keyboard operation of menus, modals, drawers.
- AA contrast verified in both themes; state never encoded by colour alone (icon/label always present).
- `prefers-reduced-motion` disables non-essential transitions; toasts stay but don't slide.

## 6. Rendered visual

A self-contained interactive rendering of these tokens, components, and hero wireframes is provided as
an Artifact (light/dark toggle). It is the visual reference the coded UI (Stage 6+) must match; the
CSS token layer in the build is generated from §1.

---

*End of Stage 4. Continue to Stage 5 — REST API specification.*
