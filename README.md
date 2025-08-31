# LillyChat

Invite-only messaging app for organizations. Built with PHP + MySQL, modeled after the Tournaments app architecture you liked:
- Three-tier-ish PHP structure with thin UI pages and helpers
- CSRF tokens, session hardening, PDO prepared statements
- Static resource versioning via filemtime
- Clean, airy UI with a deep-sunset palette

## Features

- Invite-only registration (admins send invite links via email)
- Admin vs non-admin roles
- Chats: create, join, leave, purge (admin or chat owner)
- Messages: see history, send messages (with sender full name and timestamp)
- Auth flows: login, logout, forgot/reset password, accept invite
- Account: edit first/last name and email, change password
- App settings: site title, announcement, time zone

## Project structure

- config.php, config.local.php.example
- auth.php, partials.php, mailer.php
- index.php (two-pane UI: chat list at left, thread at right)
- login.php, logout.php
- accept_invite.php (invite flow)
- forgot_password.php, reset_password.php
- change_password.php
- account.php
- admin_users.php (invite/manage members), admin_settings.php
- chat_create.php
- chat_join.php, chat_leave.php, chat_purge.php
- message_send.php
- styles.css, main.js
- schema.sql

## Requirements

- PHP 8.x with PDO MySQL extension
- MySQL (hosted at mysql.brianrosenthal.org as per your environment)
- SMTP creds (Gmail app password or other SMTP)

## Setup

1) Create config.local.php
- Copy the example and fill DB password and SMTP creds:

cp lillychat/config.local.php.example lillychat/config.local.php

Edit lillychat/config.local.php and fill:
- DB_PASS
- SMTP_USER / SMTP_PASS (Gmail app password recommended), SMTP_FROM_EMAIL
- Optional: SUPER_PASSWORD (temporary debug bypass for password verify)

2) Create database schema
- Import the schema into your database:

mysql -u lillychat -p -h mysql.brianrosenthal.org lillychat < lillychat/schema.sql

This creates:
- users, chats, chat_members, messages, settings
- seeds default settings: site_title, announcement, time_zone

3) Bootstrap the first admin
Because LillyChat is invite-only, you need one initial admin user to send invites.

Option A: Insert the first admin manually (replace values as needed):

# Generate a bcrypt hash for your chosen password (e.g., Admin123!)
php -r "echo password_hash('Admin123!', PASSWORD_DEFAULT), PHP_EOL;"

# Use the produced hash in this SQL (update email/name):
INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
VALUES ('Lilly','Rosenthal','lilly@example.com','<PASTE_HASH_HERE>',1,NOW());

Option B: Temporarily use SUPER_PASSWORD:
- Set SUPER_PASSWORD in config.local.php to your chosen string.
- Also create a user row with that email (hash can be anything); SUPER_PASSWORD only bypasses the password check if the user exists. Example:

INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verified_at)
VALUES ('Admin','User','admin@example.com','$2y$10$placeholderplaceholderplaceholderplacehold',1,NOW());

Then log in with email=admin@example.com and password=SUPER_PASSWORD, and proceed to invite others. Remove SUPER_PASSWORD before launch.

4) Run locally

From repository root (so the server sees /styles.css and PHP files in lillychat/):
php -S localhost:8000 -t lillychat

Open http://localhost:8000

## Flows

- Invite/Registration:
  - Admin goes to /admin_users.php, enters first/last/email, optional admin flag, clicks Invite.
  - User receives email with link to /accept_invite.php?token=... to set password and activate the account.
- Forgot/Reset:
  - /forgot_password.php sends a reset email to /reset_password.php?token=...
- Chats:
  - /chat_create.php to create a chat (creator auto-joins as owner)
  - Join from the left chat list or chat header if not a member
  - Leave from chat header
  - Purge (delete) by admins or chat owners from chat header
- Messages:
  - Members see history and send messages; author and timestamp are shown
- Settings:
  - /admin_settings.php manages site title, announcement, time zone
- My Profile:
  - /account.php to edit first/last name and email
  - /change_password.php to change your password

## Security notes

- CSRF protection on all POSTs via hidden csrf field
- Public-computer mode: 30-minute inactivity timeout (sliding) applied
- Sessions regenerated on login and sensitive actions
- All DB operations via prepared statements

## Styling

- Airy design and deep-sunset palette (see styles.css variables)
- Resource cache busting via filemtime query strings

## Database schema overview

- users: admin flag, invite token/expiry, reset token/expiry, timestamps
- chats: title, description, creator, timestamps
- chat_members: composite PK (chat_id, user_id), is_owner flag
- messages: body, timestamps, FK to chat/user
- settings: simple key/value store (site_title, announcement, time_zone)

## Production notes

- Configure real SMTP credentials and From address
- Consider HTTPS for all links and cookies
- Remove SUPER_PASSWORD before launch
- Regularly back up the database
