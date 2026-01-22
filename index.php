<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Time Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>PHP Time Tracker</h1>
<div id="local-clock" class="clock-display">--:--:--</div>

<div class="timer-controls">
    <span id="timer-display">00:00:00</span>
    <button id="toggle-btn" class="btn-start" onclick="toggleTimer()">Start Timer</button>
</div>

<div class="dashboard">
    <div class="filters">
        <label>From: <input type="date" id="filter-from"></label>
        <label>To: <input type="date" id="filter-to"></label>
        <button onclick="loadEntries()" class="btn-start" style="padding: 5px 10px;">Filter</button>
    </div>

    <div class="stats">
        <div class="stat-box">
            <h3>Total Time</h3>
            <span id="total-time-display">00:00:00</span>
        </div>
        <div class="stat-box">
            <h3>By Project</h3>
            <ul id="project-breakdown">
            </ul>
        </div>
    </div>
</div>

<hr>

<table>
    <thead>
    <tr>
        <th style="width: 30%;">Project / Task</th>
        <th>Start (Date & Time)</th>
        <th>End (Date & Time)</th>
        <th>Duration</th>
    </tr>
    </thead>
    <tbody id="entries-list"></tbody>
</table>

<script>
    let timerInterval;
    let currentStartTime = null;
    // cache entries to help with validation lookups
    let entriesCache = [];

    // Set default dates on load (Last 7 days)
    document.addEventListener('DOMContentLoaded', () => {
        const now = new Date();
        const lastWeek = new Date();
        lastWeek.setDate(now.getDate() - 7);

        // Format for HTML date input (YYYY-MM-DD)
        document.getElementById('filter-to').value = now.toISOString().split('T')[0];
        document.getElementById('filter-from').value = lastWeek.toISOString().split('T')[0];

        loadEntries();
        setInterval(updateLocalClock, 1000);
        updateLocalClock();
    });

    async function loadEntries() {
        // Get Dates from inputs
        const fromStr = document.getElementById('filter-from').value;
        const toStr = document.getElementById('filter-to').value;

        // Convert to Unix Timestamps (Start of day / End of day)
        const fromTs = Math.floor(new Date(fromStr).setHours(0, 0, 0, 0) / 1000);
        const toTs = Math.floor(new Date(toStr).setHours(23, 59, 59, 999) / 1000);

        const res = await fetch('api.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'list',
                from: fromTs,
                to: toTs
            })
        });
        const result = await res.json();
        entriesCache = result.data;

        renderTable(result.data);
        calculateStats(result.data); // NEW: Run the report math
        checkActiveTimer(result.data);
    }

    function calculateStats(entries) {
        let totalSeconds = 0;
        const projectMap = {};

        entries.forEach(e => {
            // If the timer is currently running, don't count it in reports yet
            // (or use current time for live approximation, but skipping is safer for now)
            if (!e.end_time) return;

            const duration = e.end_time - e.start_time;
            totalSeconds += duration;

            // Group by Project
            const pName = e.project ? e.project.trim() : 'Uncategorized';
            if (!projectMap[pName]) projectMap[pName] = 0;
            projectMap[pName] += duration;
        });

        // 1. Update Total
        document.getElementById('total-time-display').textContent = formatDuration(totalSeconds);

        // 2. Update Breakdown List
        const list = document.getElementById('project-breakdown');
        list.innerHTML = '';

        // Sort projects by most time spent
        const sortedProjects = Object.entries(projectMap).sort((a, b) => b[1] - a[1]);

        sortedProjects.forEach(([name, seconds]) => {
            const li = document.createElement('li');
            li.innerHTML = `<span class="p-name">${name}</span> <span>${formatDuration(seconds)}</span>`;
            list.appendChild(li);
        });
    }

    // --- Rendering (Stages 1, 2, 3, 4, 5, 6) ---

    function renderTable(entries) {
        const tbody = document.getElementById('entries-list');
        tbody.innerHTML = '';

        entries.forEach(entry => {
            const start = new Date(entry.start_time * 1000);
            const end = entry.end_time ? new Date(entry.end_time * 1000) : null;

            // Calculate duration or show status
            let durationDisplay = 'Running...';
            if (end) {
                durationDisplay = formatDuration((entry.end_time - entry.start_time));
            }

            // Helper to format Date object to "YYYY-MM-DDTHH:mm:ss" for input values
            const startVal = toLocalISOString(start);
            const endVal = end ? toLocalISOString(end) : '';

            const row = `
                    <tr id="row-${entry.id}">
                        <td>
                            <input type="text" class="edit-input"
                                value="${entry.project || ''}"
                                placeholder="Add Project..."
                                onblur="updateText(${entry.id}, 'project', this.value)">
                        </td>
                        <td>
                            <input type="datetime-local" step="1" class="edit-input"
                                value="${startVal}"
                                onchange="handleTimeUpdate(${entry.id}, 'start_time', this.value)">
                        </td>
                        <td>
                            <input type="datetime-local" step="1" class="edit-input"
                                value="${endVal}"
                                ${!end ? 'disabled' : ''}
                                onchange="handleTimeUpdate(${entry.id}, 'end_time', this.value)">
                        </td>
                        <td class="duration-cell">${durationDisplay}</td>
                    </tr>
                `;
            tbody.innerHTML += row;
        });
    }

    // --- Logic & Validation (Stages 7 & 8) ---

    async function handleTimeUpdate(id, field, valueStr) {
        const row = document.getElementById(`row-${id}`);
        const durationCell = row.querySelector('.duration-cell');

        // 1. Convert string input to Unix Timestamp
        if (!valueStr) return; // Don't allow clearing for now
        const newTime = Math.floor(new Date(valueStr).getTime() / 1000);

        // Find current values from cache for validation
        const entry = entriesCache.find(e => e.id == id);
        let start = field === 'start_time' ? newTime : entry.start_time;
        let end = field === 'end_time' ? newTime : entry.end_time;

        // --- VALIDATION RULES ---

        // Rule: Reasonable Year (Stage 8)
        if (newTime < 946684800) { // Jan 1, 2000
            alert("Date cannot be before year 2000");
            loadEntries(); // Reset UI
            return;
        }

        // Rule: End cannot be in the future (Stage 7)
        const now = Math.floor(Date.now() / 1000);
        if (field === 'end_time' && newTime > now) {
            alert("End time cannot be in the future.");
            loadEntries();
            return;
        }

        // Rule: Start cannot be after End (Stages 3, 5)
        // Rule: End cannot be before Start (Stages 4, 6)
        if (end && start > end) {
            alert("Start time cannot be after End time.");
            loadEntries(); // Reset UI to previous valid state
            return;
        }

        // --- SAVE & UPDATE UI ---

        // Optimistic UI Update: Calculate new duration immediately
        if (end) {
            durationCell.textContent = formatDuration(end - start);
        }

        // Send to API
        await updateEntryRequest(id, field, newTime);

        // Update local cache so next validation uses new data
        if (field === 'start_time') entry.start_time = newTime;
        if (field === 'end_time') entry.end_time = newTime;
    }

    // --- API Helpers ---

    async function updateText(id, field, value) {
        await updateEntryRequest(id, field, value);
    }

    async function updateEntryRequest(id, field, value) {
        const payload = {action: 'update', id: id};
        payload[field] = value;

        await fetch('api.php', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
    }

    // --- Utility Functions ---

    function toLocalISOString(date) {
        // JS toISOString() is UTC. We need Local time for the input.
        // We subtract the timezone offset to shift the time, then slice off the 'Z'
        const offset = date.getTimezoneOffset() * 60000;
        return (new Date(date - offset)).toISOString().slice(0, -1);
    }

    function formatDuration(seconds) {
        const h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        const m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function updateLocalClock() {
        document.getElementById('local-clock').textContent = new Date().toLocaleTimeString();
    }

    // (Existing timer logic for Start/Stop remains somewhat the same, simplified here)
    function checkActiveTimer(entries) {
        const active = entries.find(e => e.end_time === null);
        const btn = document.getElementById('toggle-btn');
        if (active) {
            currentStartTime = active.start_time;
            btn.textContent = "Stop Timer";
            btn.className = "btn-stop";
            startTicking();
        } else {
            currentStartTime = null;
            btn.textContent = "Start Timer";
            btn.className = "btn-start";
            stopTicking();
            document.getElementById('timer-display').textContent = "00:00:00";
        }
    }

    async function toggleTimer() {
        const action = currentStartTime ? 'stop' : 'start';
        await fetch('api.php', {method: 'POST', body: JSON.stringify({action: action})});
        loadEntries();
    }

    function startTicking() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            document.getElementById('timer-display').textContent = formatDuration(now - currentStartTime);
        }, 1000);
    }

    function stopTicking() {
        clearInterval(timerInterval);
    }
</script>
</body>
</html>