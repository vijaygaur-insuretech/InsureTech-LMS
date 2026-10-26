# InsureTech Skills LMS

The learning platform behind **[insureTech Skills](https://insuretechskills.tech/)** — live, instructor-led IT training and certification in cloud, DevOps, data and cybersecurity.

One system for three kinds of users:

| Role | What they do |
|---|---|
| **Admin** | Runs the platform: courses, students, trainers, batches, subscriptions (access dates), recordings, reports and settings. |
| **Trainer** | Teaches their assigned batches: course content, quizzes, assignments, grading, progress and live-class links. Sees only their own courses and learners. |
| **Student** | Learns: live classes, recordings, materials, quizzes, assignments, progress tracking and certificates. Sees only the courses they are enrolled in, while their access is valid. |

---

## Features

### Public website (no login needed)
- **Landing page** with hero, statistics, certification partners, course catalogue with category filters, "How it works", About, FAQ, call to action and a full footer with contact details and social links.
- **Public course pages** — overview, curriculum outline (module and lesson names, content stays locked), trainers, class timing and a *Log in to start learning* button that returns the learner to that course after login.
- **SEO built in** — per-page titles and meta descriptions, canonical links, Open Graph / Twitter cards, and schema.org structured data (`EducationalOrganization`, `ItemList` of `Course`, `FAQPage`, `Course`).
- **Fully editable** from the admin panel: headline, stats, partners, about text, FAQs, contact details, social links, SEO title and description.

### Student experience
- **Personal dashboard** — welcome hero, enrolled courses, average progress, completed courses.
- **Continue learning** card with progress bar, "Up next" lesson and a one-click **Resume** button.
- **Subscription awareness** — warning when access to a course is about to end; access end date shown on each course.
- **Live classes** — every course (or batch) can carry a meeting link and timing; students get a **Join live class** button that opens Google Meet / Zoom / Teams in a new tab.
- **Course progress strip** at the top of each course: percentage, activities completed, next lesson.
- **Protected recordings** — class recordings play inside the platform (Google Drive, YouTube or Vimeo), with no download button, a blocked pop-out, disabled right-click and a moving watermark with the learner's name and email.

### Trainer experience
- **Trainer dashboard** — number of courses, learners and submissions waiting for review; a card per course with batch tags and quick links to Learners, Batches, Grades and Progress.
- **Trainer tools bar** on every course they teach, including the live-class Join button and link management.
- **Classes & batches page** — edit live-class links and timings for their own courses and batches, and create new batches.
- Recordings are **hidden from trainers** by default (configurable).

### Admin portal
- **Quick actions** on the dashboard.
- **Students & trainers** — tabs for Students / Trainers / Not assigned with counts, search, pagination and status badges (*Active, Expiring soon, Expired, Suspended, Not started*).
- **Per-person management** — assign to a course and batch as student or trainer, set access start/end dates, renew, suspend / re-activate, add to or remove from batches, remove from a course (with confirmation).
- **Classes & batches** — every course's live-class link and timing in one place, per-batch link overrides, member counts, add batch.
- **Recordings** — paste a video link into any course section; only admins can add, edit or see the link.

### Learning features
- Courses → modules (sections) → lessons, with drag-and-drop reordering and publish / hide.
- Quizzes: multiple choice, multiple select, true/false and scenario questions, explanations, pass mark, 1 / 3 / unlimited attempts, full attempt history and best-score grading.
- Assignments with file or text submissions, due dates, marks, feedback and review history.
- Materials (PDF, DOCX, PPTX, XLSX, ZIP and more) stored in private storage and served only to authorised users.
- Activity completion rules, access restrictions (e.g. unlock the next lesson only after passing a quiz), gradebook and reports.
- In-app and email notifications, calendar and timeline.
- Full activity logs and audit trail.

### Training operations
- **Scheduled live sessions** per course or batch with date, time and meeting link; email and in-app reminders before each class, and notices when a class is scheduled, changed or cancelled.
- **Attendance** (Present / Absent / Late) per session, with an attendance percentage for each learner.
- **Trainer handover**: topic status (completed / pending) and private trainer notes that learners never see.
- **Certificates** with a unique ID, issued automatically on course completion (admins can override, and every override is logged). They include a public verification page and an **admin certificate designer** (colours, text, signature, logo) that applies to every certificate.
- **Learner insights**: average quiz score, attendance, weak topics (with *Review lesson* / *Retry quiz*), **Skill Passport** and **Career Readiness** scores with configurable weights.
- **Feedback**: course and trainer ratings with admin approval, plus a Google reviews link.
- **Email notifications**, each with an on/off switch and an editable template:
  - welcome email with login details (temporary password, changed at first login);
  - enrolment, new content, class reminders and certificate issued;
  - subscription expiring;
  - assignment submitted (to the trainer), new learner (to the trainer) and a weekly inactive-learner report (to admins).
- **Trainer restrictions**: trainers cannot enrol learners, change subscriptions or assign roles unless an admin allows it.

### Job Placement portal
A separate module for placement work, with three roles that each have their own dashboard: **Admin**, **Employee** and **Client** (job seeker). Everyone uses the same login.
- **Clients:** master profile (personal, professional, job preferences, work authorization, references), resume and cover-letter versions (PDF/DOC/DOCX), and their own application history and status updates.
- **Employees:** only the clients assigned to them. They record applications with job-specific screening answers, update statuses, set follow-ups and write daily work reports.
- **Admins:** create and deactivate employees and clients, assign and reassign clients (including in bulk), approve or reject resumes or request a new version, and see all applications, reports and the audit log.
- **Permission and feature control panel:** field, action and feature switches, set for all employees or per employee. Enforced on the server for every page and file; preview and download are separate.
- **Configurable without code:** industries, screening questions (short or long text, yes/no, single or multiple choice, date, number, URL; required, optional or conditional), statuses and platforms.
- **Duplicate warnings, follow-up reminders, CSV export, reports** (by status, industry, role, platform, employee and day) and notifications.

### Design
- Custom brand theme (teal / navy / gold, Inter + Poppins typography).
- Responsive from phone to desktop, light and dark mode, accessible markup (landmarks, labels, focus states, reduced-motion support).
- All platform branding is replaced with insureTech Skills branding.

---

## Tech stack

| Layer | Technology |
|---|---|
| Language | **PHP 8.3+** |
| Database | **PostgreSQL 17** (recommended). MySQL 8.4+ / MariaDB 11.4+ are also supported. |
| Templates | Mustache (server- and client-side) |
| Styling | SCSS compiled on the server, Bootstrap 5, Font Awesome 6 |
| Front-end | Vanilla JavaScript / AMD modules, ES modules |
| Dependencies | Composer (PHP), npm (front-end build tooling only) |
| Web server | Apache 2.4 or Nginx + PHP-FPM, document root set to `public/` |
| Background jobs | Cron (every minute) for notifications, subscription expiry, cleanup |
| File storage | Private data directory outside the web root |

No paid services, plugins or APIs are required. The only external resource is Google Fonts; recordings and live classes use your existing Google Drive / YouTube / Vimeo and Meet / Zoom / Teams accounts.

---

## Database

All data lives in a single relational database (PostgreSQL recommended). Key areas:

- **Users and roles** — accounts, role assignments per course, capabilities.
- **Courses** — courses, categories, sections (modules), course modules (lessons and activities).
- **Enrolments** — enrolments with **start date, end date and status**, used as the subscription model (Active / Expiring soon / Expired / Suspended are derived from these).
- **Batches** — groups and group membership within each course.
- **Assessment** — quizzes, questions, attempts, assignments, submissions, grades.
- **Progress** — activity and course completion.
- **Live classes** — custom fields on courses and batches: `itmeetinglink` (meeting URL) and `itclassschedule` (timing).
- **Recordings** — table `itrecording` (`id, course, name, intro, introformat, videourl, duration, timecreated, timemodified`).
- **Logs** — a standard event log of every important action.

The schema is created and upgraded automatically by the installer; never edit it by hand.

---

## Project structure

```
.
├── public/                         Web root (point the web server here)
│   ├── theme/insuretech/           insureTech Skills theme and portals
│   │   ├── classes/local/          Landing page, people management, live-class fields
│   │   ├── classes/output/         Dashboard panels and renderer
│   │   ├── manage/                 Admin pages: Students & trainers, Classes & batches
│   │   ├── course.php              Public course details page
│   │   ├── layout/                 Landing page and public page layouts
│   │   ├── templates/              Mustache templates
│   │   ├── scss/                   Brand design system
│   │   ├── lang/en/                All user-facing text
│   │   └── settings.php            Admin settings (branding, landing page, SEO)
│   ├── local/itskills/             Sessions, attendance, handover, certificates, insights, feedback, emails
│   ├── local/placement/            Job Placement portal: clients, employees, resumes, applications, permissions
│   └── mod/itrecording/            Protected "Recording" activity
├── deploy/                         Docker image, compose stack and VPS deploy scripts
├── .github/workflows/deploy.yml    CI/CD: check and deploy on every push to main
├── admin/                          Command-line tools (install, upgrade, cache purge…)
├── composer.json                   PHP dependencies
└── config-dist.php                 Configuration template
```

---

## Getting started

### Requirements
- PHP 8.3+ with `curl, gd, intl, mbstring, pgsql, soap, sodium, xml, xmlreader, zip, zlib, fileinfo, iconv, openssl, ctype, dom, simplexml, spl, json`
- PostgreSQL 17+ (or MySQL 8.4+ / MariaDB 11.4+)
- Composer 2
- A web server with the document root set to `public/`

### Install

```bash
# 1. Get the code
git clone https://github.com/vijaygaur-insuretech/InsureTech-LMS.git
cd InsureTech-LMS

# 2. Install PHP dependencies
composer install --no-dev

# 3. Create the database (PostgreSQL)
createdb -E UTF8 insuretech_lms

# 4. Create a data directory outside the web root, writable by the web server
mkdir -p /var/lms-data && chown www-data:www-data /var/lms-data

# 5. Copy and edit the configuration (database credentials, site URL, data directory)
cp config-dist.php config.php

# 6. Install
php admin/cli/install_database.php --agree-license \
    --fullname="insureTech Skills" --shortname="insureTech" \
    --adminuser=admin --adminpass='<choose-a-strong-password>' --adminemail=admin@example.com

# 7. Schedule background jobs (every minute)
#    * * * * * www-data php /path/to/InsureTech-LMS/admin/cli/cron.php
```

Then sign in as the admin and:

1. **Site administration → Appearance → Themes** → select **insureTech Skills**.
2. **Site administration → Security → Site security settings** → turn **off** *Force users to log in* (so visitors can see the landing page).
3. **Site administration → Appearance → Navigation** → turn **on** *Enable Home*.
4. Review the theme settings (below) and the Recording settings.

### Upgrading
After pulling new code, run:

```bash
composer install --no-dev
php admin/cli/upgrade.php --non-interactive
```

---

## Configuration

Everything is configurable from the admin panel — no code changes needed.

| Where | What |
|---|---|
| Appearance → Themes → insureTech Skills → **General** | Brand colour |
| … → **Landing page** | Hero text, stats, partners, About, FAQs, call to action, contact details, social links, SEO title and description |
| … → **Advanced** | Custom SCSS / CSS |
| Plugins → Activity modules → **Recording** | *Trainers can watch recordings*, *Name watermark*, *Block the pop-out button* |
| Users → **Define roles** | Fine-grained permissions, e.g. `mod/itrecording:view`, `:addinstance`, `:manage` |
| Course settings → **Live classes** | Course meeting link and class timing |
| Groups → Edit group → **Live classes** | Batch-specific meeting link and timing |
| Language → **Language customisation** (`theme_insuretech`) | Any label or text on the site |

### Recording setup (Google Drive)
1. In Google Drive, open the video → **Share** → *Anyone with the link* → **Viewer**.
2. In the share settings, turn **off** *Viewers can download, print and copy*.
3. Copy the link, then in the course: **Edit mode → Add an activity → Recording** → paste the link.

---

## Security

- Passwords stored only as salted one-way hashes (upgraded automatically when the algorithm changes), session-based authentication, CSRF protection on every form.
- Role-based permissions enforced on the server for every page, file and API request — hiding something in the UI is never the only protection.
- Expired or suspended subscriptions lose course access automatically.
- Private file storage outside the web root; files are served only after permission checks.
- Login lockout after repeated failed attempts; optional multi-factor authentication.
- Full audit log of user actions.
- Recording links are visible only to admins; recordings are excluded from course backups so links do not leak through exported files.

> No browser-based video player can make screen recording impossible. Recordings use download restrictions, a blocked pop-out and a personal watermark to discourage sharing.

---

## Deployment (CI/CD)

Every push to `main` runs **GitHub Actions**:
1. Lint the custom code and build the Docker image.
2. Copy the code to the VPS over SSH.
3. Build the image there, then install the site on the first run or upgrade the database on later runs.
4. Restart the containers and health-check the login page.

The LMS runs as an isolated Docker Compose stack (PHP 8.3 + Apache, PostgreSQL 17, a cron worker). It listens only on `127.0.0.1`, behind the server's existing web server, so other sites on the same VPS are not affected. Database passwords and the admin password live in `/opt/insuretech-lms/.env` on the server, never in git.

The one-time server setup, GitHub secrets and web-server snippet are in [deploy/README.md](deploy/README.md).

---

## Roadmap

- Resume videos from the last position (needs a video provider with a player API instead of Google Drive)
- Signed, expiring video links (same reason)
- AI-based career recommendations
- Automatic display of Google reviews

---

## License

This project is open source and distributed under the **GNU General Public License v3 or later** — see [`COPYING.txt`](COPYING.txt). It includes third-party open-source components, each under its own licence; see the `thirdpartylibs.xml` files in the source tree.

© insureTech Skills — 44 A, IT Park, Sahastradhara Road, Dehradun, Uttarakhand 248001, India · [support@insuretechskills.tech](mailto:support@insuretechskills.tech)
