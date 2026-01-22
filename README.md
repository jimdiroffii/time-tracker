# Simple PHP Time Tracker

![Application Screenshot](screenshots/php-time-tracker.png)

A "bare metal" self-hosted time tracking application built with pure PHP, SQLite, and Vanilla JavaScript. Designed to be
fast, lightweight, and framework-free.

## Features

* **Zero Dependencies:** No frameworks, no Composer, no `node_modules`. Just drop the files and run.
* **Self-Contained Database:** Uses a single SQLite file (`tracker.sqlite`) that is automatically created on first run.
* **Single Page Application (SPA) Feel:** Uses `fetch` API for instant interactions without page reloads.
* **Reporting:** Filter time entries by date range and view a breakdown by project.
* **Privacy Focused:** Data never leaves your server.

## Prerequisites

* **PHP 7.4+** (Works with PHP 8.x)
* **PHP SQLite Extension** (Usually enabled by default: `extension=pdo_sqlite`)

## Installation

1. **Clone or Download** this repository to your web server.
2. **Set Permissions:** Ensure the web server has **write permissions** to the project folder. This is required because
   the application needs to create and write to the `tracker.sqlite` database file.

## Usage

### Quick Start (Development / Testing)

You can run this immediately using PHP's built-in web server. Open your terminal in the project directory and run:

```bash
php -S localhost:8000
```

Open your browser to `http://localhost:8000`.

## Docker

Clone the repo. Run docker compose. The application will be available on `8080`.

```bash
docker compose up -d
```

