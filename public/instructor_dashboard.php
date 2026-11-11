<?php
require_once('../../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/instructor_dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Pearson Skilling Instructor');
$PAGE->set_heading('Pearson Skilling Instructor');

echo $OUTPUT->header();
?>

<style>
.pearson-main-container {
    display: flex;
    font-family: 'Open Sans', Arial, sans-serif;
    background-color: #f7fafc;
    min-height: 80vh;
    margin: -20px;
}
.pearson-sidebar {
    width: 280px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    padding: 20px 0;
    flex-shrink: 0;
}
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}
.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 12px 24px;
    color: #2d3748;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
}
.sidebar-menu li a:hover, .sidebar-menu li.active a {
    background-color: #f3f4f6;
    color: #5b21b6;
    border-left: 4px solid #5b21b6;
}
.sidebar-menu li a svg {
    margin-right: 12px;
    width: 18px;
    height: 18px;
    fill: currentColor;
}
.sidebar-section-title {
    font-size: 11px;
    font-weight: 700;
    color: #718096;
    padding: 20px 24px 8px 24px;
    letter-spacing: 0.5px;
}
.pearson-content-area {
    flex-grow: 1;
    padding: 30px;
}
.welcome-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 25px;
    color: #1a202c;
}
.pearson-schedule-card, .pearson-assigned-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 30px;
    overflow: hidden;
}
.schedule-header {
    background-color: #5b21b6;
    color: #ffffff;
    padding: 15px 24px;
    font-size: 18px;
    font-weight: 700;
}
.schedule-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px 40px;
}
.schedule-text {
    color: #718096;
    font-size: 16px;
}
.mini-calendar {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    width: 280px;
    background: #fff;
}
.cal-header {
    text-align: center;
    font-weight: 700;
    margin-bottom: 10px;
    color: #1a202c;
}
.cal-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    text-align: center;
    font-size: 12px;
    color: #4a5568;
}
.cal-days span.today {
    background: #5b21b6;
    color: white;
    border-radius: 50%;
    font-weight: bold;
    display: inline-block;
    width: 20px;
    height: 20px;
    line-height: 20px;
}
.pearson-assigned-card {
    padding: 24px;
}
.section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    color: #1a202c;
}
.classes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    border-top: 1px solid #edf2f7;
    border-bottom: 1px solid #edf2f7;
    padding: 20px 0;
}
.class-item {
    padding-right: 15px;
    border-right: 1px solid #edf2f7;
}
.class-item:last-child {
    border-right: none;
}
.class-code {
    color: #5b21b6;
    font-weight: 700;
    font-size: 16px;
    text-decoration: none;
}
.class-code:hover {
    text-decoration: underline;
}
.class-desc {
    font-size: 11px;
    color: #718096;
    margin: 5px 0 10px 0;
}
.class-date {
    font-size: 12px;
    color: #a0aec0;
}
.view-all-container {
    text-align: center;
    margin-top: 20px;
}
.view-all-btn {
    color: #5b21b6;
    font-weight: 700;
    text-decoration: none;
}
.view-all-btn:hover {
    text-decoration: underline;
}
</style>

<div class="pearson-main-container">
    <!-- Sidebar Menu -->
    <div class="pearson-sidebar">
        <ul class="sidebar-menu">
            <li class="active">
                <a href="#">
                    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    Home
                </a>
            </li>
            <li>
                <a href="#">
                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                    Students
                </a>
            </li>
            <li>
                <a href="#">
                    <svg viewBox="0 0 24 24"><path d="M4 10h12v2H4zm0-4h12v2H4zm0 8h8v2H4zm10 0l6 4-6 4z"/></svg>
                    Classes
                </a>
            </li>
            <li>
                <a href="#">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    My profile
                </a>
            </li>
            <li>
                <a href="#">
                    <svg viewBox="0 0 24 24"><path d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.03 2.18 13.84 2 13.6 2h-4c-.24 0-.43.18-.47.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.04.24.23.42.47.42h4c.24 0 .43-.18.47-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z"/></svg>
                    Skilling Administrator
                </a>
            </li>
        </ul>

        <div class="sidebar-section-title">HELP AND SUPPORT</div>
        <ul class="sidebar-menu">
            <li>
                <a href="#" target="_blank">
                    <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                    Knowledge Center ↗
                </a>
            </li>
            <li>
                <a href="#" target="_blank">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                    Contact Support ↗
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="pearson-content-area">
        <h2 class="welcome-title">Welcome <?php echo fullname($USER); ?></h2>
        
        <!-- Class Schedule Section -->
        <div class="pearson-schedule-card">
            <div class="schedule-header">Class schedule</div>
            <div class="schedule-body">
                <div class="schedule-text">No classes scheduled at this time</div>
                <div class="mini-calendar">
                    <div class="cal-header">November 2026</div>
                    <div class="cal-days">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                        <span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span class="today">6</span><span>7</span>
                        <span>8</span><span>9</span><span>10</span><span>11</span><span>12</span><span>13</span><span>14</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Classes Section -->
        <div class="pearson-assigned-card">
            <h3 class="section-title">Assigned Classes</h3>
            <div class="classes-grid">
                <div class="class-item">
                    <a href="#" class="class-code">AI-900</a>
                    <p class="class-desc">MICROSOFT AZURE AI FUNDAMENTALS (AI-900)</p>
                    <span class="class-date">📅 May 5 - Jun 30, 2026</span>
                </div>
                <div class="class-item">
                    <a href="#" class="class-code">CCNA</a>
                    <p class="class-desc">CISCO CERTIFIED NETWORK ASSOCIATE CCNA (200-301 V1.1)</p>
                    <span class="class-date">📅 Sep 24, 2026 - Dec 31, 2027</span>
                </div>
                <div class="class-item">
                    <a href="#" class="class-code">CCNP ENCOR</a>
                    <p class="class-desc">CISCO CCNP AND CCIE ENTERPRISE CORE ENCOR (350-401 V1.1)</p>
                    <span class="class-date">📅 Sep 24, 2026 - Dec 31, 2027</span>
                </div>
            </div>
            <div class="view-all-container">
                <a href="#" class="view-all-btn">View all</a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();