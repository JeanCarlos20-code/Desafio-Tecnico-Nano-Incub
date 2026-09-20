# Architecture

## Overview

The project is a single Laravel application with React rendered through Inertia.js 2.

Do not split the project into separate frontend and backend applications.

```text
React
  ↓
Service
  ↓
Inertia
  ↓
Laravel Route
  ↓
Controller
  ↓
Request Validation
  ↓
Use Case
  ↓
Domain
  ↓
Repository Interface
  ↑
Infrastructure Repository
  ↓
Eloquent / MySQL
```

## Backend

### Domain

`Domain` contains business concepts and contracts.

Rules:

- Pure PHP only.
- Must not depend on Laravel or external frameworks.
- Must not import `Illuminate`.
- Entities contain domain state and behavior.
- Repository interfaces define persistence contracts.

### Application

`Application` orchestrates use cases.

Rules:

- Depends on Domain abstractions.
- Must not depend on HTTP, Inertia, Eloquent or Laravel Requests.
- Business workflows belong here when they are not entity-specific.
- Application errors live in `Errors`.

### Infrastructure

`Infra` contains framework and technical details.

#### Database

Contains:

- Eloquent models.
- Repository implementations.
- Persistence-specific code.

Repository implementations must satisfy interfaces defined by the Domain.

#### HTTP

Contains:

- Controllers.
- Laravel Form Requests.

Controllers must be thin and only adapt HTTP input/output to application use cases.

Administrator login uses Breeze `AuthenticatedSessionController` and `LoginRequest::authenticate()`.

Form Requests validate HTTP input such as:

- required fields;
- string/integer types;
- UUID format;
- date format.

Business rules must not rely only on HTTP validation.

## Frontend

React lives in `resources/js` and communicates with Laravel through Inertia.

### Pages

Route-level screens rendered by Inertia.

Pages should compose components and coordinate page behavior.

### Components

Reusable UI components.

Feature-specific components should remain close to their feature when possible.

### Services

Services isolate Inertia navigation and mutations from React pages/components.

Avoid spreading route URLs and `router.post`, `router.put`, or `router.delete` calls across the UI.

### Hooks

Create hooks only for reusable React behavior.

Do not create hooks only to add another abstraction layer.

## Dependency Rules

Allowed direction:

```text
Infra → Application → Domain
```

Domain must never depend on Application or Infrastructure.

Application must never depend on Infrastructure.

Framework-specific code belongs at the edges.

## General Rules

- Keep business rules on the backend.
- Frontend validation is only for UX; backend validation remains authoritative.
- Prefer small, focused abstractions.
- Do not add DTOs, services, mappers, factories or other layers without a concrete need.
- Keep the implementation idiomatic to Laravel and React while preserving the architectural boundaries above.
