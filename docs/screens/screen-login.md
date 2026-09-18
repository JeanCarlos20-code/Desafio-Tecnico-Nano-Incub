# Login Screen

## Overview

This screen authenticates an administrator and grants access to the protected area of the **ReservaSalas** system.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, and element placement:

![Visual reference for the login screen](.local/image/screen-login.png)

Reference file:

```text
.local/image/screen-login.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, validation rules, and accessibility requirements defined in this document.

- **Page route:** `GET /` (guest home) and `GET /login`
- **Submission route:** `POST /login`
- **Access:** guests only
- **Redirect after success:** `/reservations`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel authentication

## Goal

Allow a registered administrator to securely access the system using:

- email address;
- password.

All other panel routes must require an authenticated session. Unauthenticated users attempting to access a protected route must be redirected to `/login`.

## Visual structure

The page fills the available viewport and displays a centered card divided into two columns on desktop.

### Left column — visual identity

The left column uses a meeting room image with a dark navy overlay to keep the foreground content readable.

Elements:

1. Calendar and clock icon.
2. `ReservaSalas` wordmark, with `Reserva` in white and `Salas` in blue.
3. Institutional copy:

   ```text
   Salas organizadas.
   Reuniões que acontecem.
   ```

4. Supporting message positioned near the bottom:

   ```text
   Mais produtividade
   para o seu time.
   ```

### Right column — authentication form

Main content:

- heading `Acesse sua conta`;
- supporting text `Entre para gerenciar as salas e reservas.`;
- email field;
- password field;
- primary button `Entrar`;
- divider `Não tem uma conta?`;
- secondary control `Ir para o cadastro` that opens `/register`.

The screen must not include password recovery because it is outside the scope of the technical challenge. Administrator registration is enabled, so the login screen includes the cadastro control.

## Form fields

| Field | Type | Required | Autocomplete | Placeholder | Icon |
| --- | --- | --- | --- | --- | --- |
| E-mail | `email` | Yes | `email` | `seu@email.com` | Envelope |
| Senha | `password` | Yes | `current-password` | Password characters | Lock |

The form must initially focus the email field only when doing so does not interfere with navigation or accessibility.

## Validation

Validation and authentication must be performed on the server through Laravel's authentication resources. Client-side validation may improve feedback, but it must never replace server-side checks.

### Email

- required;
- must be a valid email address;
- maximum length of 255 characters;
- surrounding whitespace must be removed before authentication;
- comparison should follow the email normalization strategy adopted by the application.

### Password

- required;
- must be submitted securely;
- must never be written to logs, persisted in plain text, or returned in a response.

### Invalid credentials

The system must display a generic message without revealing whether the email exists:

```text
E-mail ou senha inválidos.
```

Do not use separate messages such as `E-mail não encontrado` or `Senha incorreta`, because they would allow account enumeration.

## Behavior

### Successful authentication

1. The administrator fills in the email and password fields.
2. The frontend submits the form to Laravel through Inertia.
3. The backend validates the input and verifies the credentials.
4. Laravel regenerates the session identifier after successful authentication.
5. The administrator is redirected to `/reservations`.

If the administrator was redirected to the login page from a protected route, the application may return them to the originally requested route after successful authentication.

### Validation error

- preserve the email value;
- clear the password field;
- visually highlight the invalid field when applicable;
- display validation messages below their corresponding fields;
- move focus to the first invalid field or to an accessible error summary.

Suggested validation messages:

```text
Informe seu e-mail.
Informe um endereço de e-mail válido.
Informe sua senha.
```

### Invalid credentials

- preserve the email value;
- clear the password field;
- display the generic authentication error above the form or below the password field;
- return focus to the password field;
- do not indicate which credential was incorrect.

### Loading state

While the authentication request is being processed:

- disable the submit button to prevent repeated clicks;
- keep the fields visible;
- change the button label to `Entrando...`;
- display a loading indicator;
- prevent duplicate submissions until the request finishes.

### Unexpected failure

If an error unrelated to validation or invalid credentials occurs, display a general message above the form:

```text
Não foi possível entrar. Tente novamente.
```

### Already authenticated user

An authenticated administrator who accesses `/` or `/login` must be redirected to `/reservations` instead of seeing the login form again.

## Navigation

| Element | Destination or action |
| --- | --- |
| `Entrar` button | Submits the authentication form |
| Enter key inside a field | Submits the authentication form |
| `Ir para o cadastro` | Navigates to `/register` |

There is no password recovery link because password recovery is not required by the challenge.

## Security requirements

- use Laravel's official authentication implementation or an official starter kit;
- protect the form with Laravel's CSRF mechanism;
- regenerate the session identifier after successful authentication;
- invalidate the session and regenerate the CSRF token during logout;
- use the password hash verification provided by Laravel;
- never compare passwords manually or store them in plain text;
- apply rate limiting to repeated login attempts;
- use a generic invalid-credentials message;
- do not expose sensitive information in logs, query strings, or frontend props;
- ensure authentication cookies use the environment-appropriate security settings.

## Responsiveness

### Desktop and landscape tablet

- center the card and apply a maximum width;
- use two columns with proportions close to `46% / 54%`;
- keep the image and institutional content visible;
- vertically center the authentication form inside the right column.

### Portrait tablet and mobile

- use a single-column layout;
- hide the large image or reduce it to a compact header;
- keep the `ReservaSalas` wordmark visible near the top;
- make fields and the primary button fill the available width;
- maintain at least `24px` of horizontal spacing;
- prevent horizontal scrolling;
- ensure the form remains usable when the virtual keyboard is open.

## Accessibility

- associate each `label` with its corresponding field;
- mark required fields programmatically;
- provide a visible focus state for fields and buttons;
- support complete keyboard navigation;
- connect error messages using `aria-invalid` and `aria-describedby`;
- use `aria-live="polite"` for authentication errors and state changes;
- keep the submit button label available to assistive technologies during loading;
- maintain sufficient contrast between text, backgrounds, borders, and buttons;
- do not rely only on color to communicate errors.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Page background | Very light blue-gray |
| Card | White, rounded corners, and a soft shadow |
| Institutional panel | Meeting room image with a dark navy overlay |
| Primary color | Bright blue |
| Heading | Near-black navy blue with a strong font weight |
| Supporting text | Blue-gray |
| Fields | White background, light-gray border, and rounded corners |
| Primary button | Blue background, white text, and full width |

## Required states

- empty form;
- focused email field;
- focused password field;
- validation error;
- invalid credentials;
- authentication in progress;
- successful authentication;
- unexpected server failure.

## Acceptance criteria

- [ ] The page is available at `/` and `/login` for unauthenticated users.
- [ ] Authenticated users who access `/` or `/login` are redirected to `/reservations`.
- [ ] The login screen includes `Ir para o cadastro`, which navigates to `/register`.
- [ ] Email and password are required.
- [ ] Laravel validation errors are displayed next to their corresponding fields.
- [ ] Invalid credentials produce a generic error message.
- [ ] The password field is cleared after a failed authentication attempt.
- [ ] The form cannot be submitted more than once while processing.
- [ ] A successful login regenerates the session and redirects the administrator to `/reservations`.
- [ ] Unauthenticated users cannot access protected panel routes.
- [ ] No password recovery flow is included.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The layout follows `.local/image/screen-login.png` as its visual reference.
