# ACE Social Planner

## Plugin Name
**ACE Social Planner (Adaptive Content Engine)**

---

## Overview

Use this file as the working guide for changes in this repository. Keep implementation aligned with the current codebase first, then extend toward the planned product shape below.

Current state:
- WordPress plugin bootstrap in `ace-social-planner.php`
- REST route registration in `includes/class-api.php`
- OpenAI request wrapper in `includes/class-ai.php`
- Minimal React admin entry in `admin/src/App.jsx`

When adding features, preserve small, testable increments and keep secrets server-side.

---

## Architecture

### Tech Stack

- PHP for WordPress backend code
- React for admin UI
- WordPress REST API for browser-to-server communication
- OpenAI Responses API for AI generation
- WP Cron, custom tables, and social adapters are planned, not implemented yet

Prefer extending existing classes before introducing new layers.

---

## Directory Structure

```text
ace-social-planner/
├── ace-social-planner.php
├── includes/
│   ├── class-api.php
│   └── class-ai.php
├── admin/
│   └── src/
│       └── App.jsx
├── README.md
└── AGENTS.md
```

Planned modules such as scheduler, tracking, blocks, and DB classes should be added only when their first real use case is ready.

---

## Database Schema

No custom tables exist yet. If queueing or analytics is added, create activation-safe schema code and document each table here before rollout.

---

## REST API

Namespace: `ace-social/v1`

Current route:
- `POST /ai/generate`

Rules:
- Every route must declare a strict `permission_callback`
- Sanitize request input before use
- Return WordPress-friendly error objects for remote API failures

---

## AI Engine

### File: `includes/class-ai.php`

Responsibilities:
- Build prompt inputs
- Call the Responses API
- Normalize response handling
- Add validation and error handling before expanding features

Do not expose API keys to JavaScript or REST responses.

---

## Scheduler System

Planned. If added, use WP Cron with explicit job names and idempotent handlers. Avoid hidden side effects in cron callbacks.

---

## Social Adapter Layer

Planned. Keep each platform adapter isolated behind a small interface so posting logic and credential handling stay separate.

---

## Tracking System

Planned. Add tracking only after there is a clear reporting surface in the admin UI.

---

## Gutenberg Blocks

Planned. Do not add blocks until the data model and REST endpoints they rely on are stable.

---

## React Admin UI

### Entry: `admin/src/App.jsx`

Keep admin code thin: fetch data from REST endpoints, render state clearly, and avoid embedding business logic in components.

---

## Settings

Store plugin settings in `wp_options` and sanitize on save. API keys, tokens, and automation flags must remain server-managed.

---

## Build Steps

1. Harden the existing AI route with validation and error handling.
2. Add settings storage for the OpenAI key.
3. Introduce a real admin screen and REST-backed actions.
4. Add scheduler and queue persistence only when publishing flows are defined.
5. Add tracking and platform adapters after the core planner works.
6. Document each new module here when it becomes real.
