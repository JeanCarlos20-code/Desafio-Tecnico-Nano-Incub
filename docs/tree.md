# Project Tree

```text
app/
└── Modules/
    └── <Module>/
        ├── Domain/
        │   ├── Entities/
        │   └── Repositories/
        │
        ├── Application/
        │   ├── UseCases/
        │   └── Errors/
        │
        └── Infra/
            ├── Database/
            │   ├── Models/
            │   └── Repositories/
            │
            └── Http/
                ├── Controllers/
                └── Requests/

resources/
└── js/
    ├── Pages/
    │   └── <Feature>/
    │       ├── Index.jsx
    │       ├── Create.jsx
    │       ├── Edit.jsx
    │       └── Components/
    │
    ├── Components/
    ├── Layouts/
    ├── Services/
    ├── Hooks/
    └── app.jsx
```

## Notes

- `app/Modules` contains backend modules.
- `Domain` must remain pure PHP.
- `Application` contains use cases and application errors.
- `Infra` contains Laravel, Eloquent, HTTP and framework-specific code.
- `Pages` contains Inertia pages.
- `Components` contains reusable React UI.
- `Services` centralizes frontend communication with Laravel/Inertia.
- `Hooks` must only be created when reusable React behavior exists.
