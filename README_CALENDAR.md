# 📅 TeamCraft Calendar Implementation Guide

This document explains the technical architecture and implementation details of the Event Calendar feature for the TeamCraft project.

## 1. Overview
The calendar provides a centralized, interactive view of all gaming events. It is designed with a "Gaming/Neon" aesthetic and features real-time data synchronization.

## 2. Technology Stack
- **Frontend**: [FullCalendar JS (v6.1.10)](https://fullcalendar.io/) - MIT Licensed (Open Source).
- **Backend**: Symfony 6/7 PHP Framework.
- **Data Format**: JSON (Asynchronous AJAX).
- **Styling**: Vanilla CSS with CSS3 Keyframe Animations.

---

## 3. System Architecture
The implementation follows a **Decoupled Architecture**:

1.  **Backend (Provider)**: A Symfony Controller acts as a REST API endpoint, fetching data from the Database and converting it to JSON.
2.  **Frontend (Consumer)**: JavaScript initializes the calendar UI and fetches the JSON data asynchronously without reloading the page.

---

## 4. Backend Implementation (The API)
**File:** `src/Controller/FrontEventController.php`

### Key Method: `calendarData()`
This method is mapped to the route `/api/my-events/calendar`.
- **Real-time Status Sync**: Before serving data, it calls `updateEventStatus()`. This dynamically calculates if an event is `OPEN`, `CLOSED` (at capacity), or `OVER` (date passed).
- **Entity Mapping**: It transforms the `Evenement` database objects into the standard "Event Object" format expected by FullCalendar.

```php
// Example Transformation
$eventsData[] = [
    'title' => ($isParticipating ? '★ ' : '') . $event->getNomEvenement(),
    'start' => $event->getDateDebut()->format(DateTime::ISO8601),
    'color' => $color, // Dynamic coloring based on status
    'extendedProps' => [
        'status' => $event->getStatus(),
        'place'  => $event->getPlace()->getNomPlace()
    ]
];
```

---

## 5. Frontend Implementation (The UI)
**File:** `templates/frontoffice/events/index.html.twig`

### Custom Features:
- **AJAX Loading**: The calendar uses the `events: '/api/my-events/calendar'` property to fetch data only when the modal opens.
- **The "Living Square" UI**: Using the `eventContent` hook, we injected custom HTML to render neon-glowing squares.
- **Neon Glow Animations**: CSS keyframes (`pulse-green`, `pulse-red`) are used to create the "pulsing" effect for active events.
- **Bootstrap Integration**: The calendar is hosted inside a `modal-xl`, with a "re-render" trigger to ensure the layout computes correctly when the modal transitions.

---

## 6. Business Rules
- **🟢 GREEN**: Open events (Joinable).
- **🔴 RED**: Closed events (No more spots).
- **⚪ GREY**: Past events.
- **🏃 RUNNER ICON**: Indicates the currently logged-in user is already a participant.

---

## 7. Educational Highlights (For the Professor)
- **Asynchronous Patterns**: Use of AJAX to improve UX and reduce server load.
- **CSS3 Mastery**: Advanced use of filters (`backdrop-filter: blur`) and shadows.
- **Clean Code**: Logic separation (Controller handles Data, Twig handles Layout, CSS handles Style).
