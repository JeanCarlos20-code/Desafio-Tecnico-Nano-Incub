# Create User Screen

## Overview

This screen allows a new administrator account to be created in the **ReservaSalas** system.

## Visual reference

The implementation must use the following image as a reference for composition, visual identity, hierarchy, spacing, and element placement:

![Visual reference for the create user screen](.local/image/screen-create-user.png)

Reference file:

```text
.local/image/screen-create-user.png
```

> The image guides the appearance of the screen, but its exact dimensions do not need to be reproduced. The layout must adapt responsively to the available space while preserving the behaviors, validations, and accessibility requirements defined in this document.

- **Route:** `/register`
- **Submission method:** `POST /register`
- **Access:** public only when administrator registration is enabled; preferably restricted to an authenticated administrator
- **Redirect after success:** `/reservations`
- **Expected technologies:** React, Inertia.js 2, Tailwind CSS, and Laravel authentication

> The technical challenge requires administrative login, but it does not require public registration. If this screen is included in the final delivery, the access policy and the reason for including it must be explained in the `README.md`. Allowing any visitor to create an administrator account is not recommended in production.

## Goal

Allow an administrator account to be created through a simple form containing only:

- name;
- email address;
- password.

## Visual structure

The page fills the available viewport and displays a centered card divided into two columns on desktop.

### Left column — visual identity

The left column has a dark background over a meeting room image, with an overlay that keeps the text readable.

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
   Comece agora e ajude
   a manter o seu time
   mais produtivo.
   ```

### Right column — form

Main content:

- heading `Criar usuário`;
- supporting text `Preencha os dados para criar uma nova conta de administrador.`;
- name, email, and password fields;
- primary button `Criar usuário`;
- divider with the text `Já tem uma conta?`;
- secondary button `Ir para o login`.

## Form fields

| Field | Type | Required | Autocomplete | Placeholder | Icon |
| --- | --- | --- | --- | --- | --- |
| Nome | `text` | Yes | `name` | `Seu nome completo` | User |
| E-mail | `email` | Yes | `email` | `seu@email.com` | Envelope |
| Senha | `password` | Yes | `new-password` | `Mínimo de 6 caracteres` | Lock |

The password field includes a button on the right that toggles between visible and hidden password states. This control must have an accessible name such as `Mostrar senha` or `Ocultar senha`.

## Validation

All validation must run on the server. Validation errors returned by Laravel must be displayed directly below the corresponding field.

### Name

- required;
- must be a string;
- maximum length of 255 characters.

### Email

- required;
- must be a valid email address;
- maximum length of 255 characters;
- must be unique among registered users;
- must be normalized before persistence by trimming surrounding whitespace and converting it to lowercase.

### Password

- required;
- minimum length of 6 characters, as shown in the design;
- must only be stored as a hash generated through Laravel's built-in resources;
- must never be written to logs or returned in a response.

> If the project adopts the starter kit's default policy of at least 8 characters, the placeholder and interface validation message must also be changed to 8 so that the frontend and backend remain consistent.

## Behavior

### Successful submission

1. The user fills in all three fields.
2. The frontend submits the form to the backend through Inertia.
3. The backend validates the data and creates the account with an administrator role.
4. The password is persisted only after hashing.
5. The system authenticates the newly created user and redirects them to `/reservations`.

### Validation error

- preserve the name and email values;
- clear the password field;
- visually highlight invalid fields;
- display the backend message below each corresponding field;
- move focus to the first invalid field or to an accessible error summary.

Suggested messages:

```text
Informe seu nome.
Informe um endereço de e-mail válido.
Este e-mail já está cadastrado.
A senha deve possuir pelo menos 6 caracteres.
```

### Loading state

While the request is being processed:

- disable the primary button to prevent repeated clicks;
- keep the fields visible;
- change the button label to `Criando usuário...`;
- display a loading indicator;
- prevent duplicate submissions until the request finishes.

### Unexpected failure

If an error unrelated to validation occurs, display a general message above the form:

```text
Não foi possível criar o usuário. Tente novamente.
```

## Navigation

| Element | Destination or action |
| --- | --- |
| `Criar usuário` button | Submits the form |
| `Ir para o login` button | Navigates to `/login` |
| Password field control | Toggles password visibility |

## Responsiveness

### Desktop and landscape tablet

- center the card and apply a maximum width;
- use two columns with proportions close to `44% / 56%`;
- keep the image and institutional content visible;
- vertically center the form inside the right column.

### Portrait tablet and mobile

- use a single-column layout;
- hide the image or reduce it to a compact header;
- keep the wordmark at the top of the form;
- make fields and buttons fill the available width;
- maintain at least `24px` of horizontal spacing;
- prevent horizontal scrolling.

## Accessibility

- associate each `label` with its corresponding field;
- combine the visual asterisk with `aria-required="true"` on required fields;
- provide a visible focus state for fields, links, and buttons;
- support complete keyboard navigation;
- connect error messages using `aria-invalid` and `aria-describedby`;
- use `aria-live="polite"` for general messages and state changes;
- maintain sufficient contrast between text, backgrounds, borders, and buttons;
- do not rely only on red to communicate errors.

## Visual guidelines

| Element | Guideline |
| --- | --- |
| Page background | Very light blue-gray |
| Card | White, rounded corners, and a soft shadow |
| Institutional panel | Navy blue with a dark overlay |
| Primary color | Bright blue |
| Headings | Near-black navy blue with a strong font weight |
| Supporting text | Blue-gray |
| Fields | White background, light-gray border, and rounded corners |
| Primary button | Blue background, white text, and full width |
| Secondary button | White background, light-blue border, and blue text |

## Required states

- empty form;
- focused field;
- visible and hidden password;
- one or more validation errors;
- submission in progress;
- successful registration;
- unexpected server failure.

## Acceptance criteria

- [ ] The page is available at `/register` when registration is enabled.
- [ ] Name, email, and password are required.
- [ ] Laravel validation errors are displayed next to their corresponding fields.
- [ ] An email address that is already registered cannot be used again.
- [ ] The password is never stored as plain text.
- [ ] The password visibility control works and can be operated with the keyboard.
- [ ] The form cannot be submitted more than once while processing.
- [ ] After successful registration, the user is authenticated and redirected to `/reservations`.
- [ ] The `Ir para o login` button navigates to `/login`.
- [ ] The screen works correctly on desktop, tablet, and mobile.
- [ ] The administrator registration access policy is documented in the `README.md`.
