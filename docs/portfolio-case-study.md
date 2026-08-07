# Portfolio Case Study

## Project

YouPanel is a private server cockpit for Youssef and trusted collaborators. It demonstrates full-stack product engineering, Laravel API design, Next.js application architecture, DevOps integration planning and application security.

## Problem

Managing a personal Ubuntu server through repeated terminal commands is powerful but uncomfortable for beginners and risky when routine tasks touch live websites.

## Solution

YouPanel provides an authenticated dashboard with server metrics, website inventory, safe file browsing, operational actions, logs, backups, health checks, notifications, Coolify integration foundations, deployment approvals and a restricted project console.

## Security Decisions

- No public registration.
- Sanctum cookie authentication instead of localStorage tokens.
- Role and website-membership authorization enforced in Laravel.
- TOTP two-factor authentication with encrypted hashed recovery-code storage.
- Backend-only Coolify token handling.
- Allowlisted service and console actions.
- No Docker socket access and no host terminal.
- Production checks and permission audits are read-only commands.

## Stack

Laravel 12, PHP 8.3-compatible runtime, MySQL, Sanctum, Pest, Pint, Next.js 16 App Router, TypeScript, Tailwind CSS, TanStack Query, React Hook Form, Zod, Axios, Motion, Lucide and Monaco.

## Phase 2 Ideas

- Real production deployment with Cloudflare/Nginx runbook execution.
- Browser E2E tests.
- Verified Coolify API integration against the real instance.
- Webhook ingestion with signature validation.
- Rollback workflows where the provider supports them safely.
