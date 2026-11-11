<?php
require_once('../config.php');
require_login();

$PAGE->set_url(new moodle_url('/instructor_dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Pearson Skilling Instructor');
$PAGE->set_heading('Pearson Skilling Instructor');

echo $OUTPUT->header();

// Database se current instructor ke assigned courses fetch karne ka code
global $USER, $DB;
$sql = "{courses} c JOIN {context} ctx ON ctx.instanceid = c.id 
        JOIN {role_assignments} ra ON ra.contextid = ctx.id 
        WHERE ra.userid = :userid AND ctx.contextlevel = 50";
        
// Agar upar wala complex query na chale toh simple enrolled courses ye hain:
$my_courses = enrol_get_users_courses($USER->id);
?>

<style>
/* Aapka wahi CSS yahan rahega */
.pearson-main-container { display: flex; font-family: 'Open Sans', Arial, sans-serif; background-color: #f7fafc; min-height: 80vh; margin: -20px; }
.pearson-sidebar { width: 280px; background: #ffffff; border-right: 1px solid #e2e8f0; padding: 20px 0; flex-shrink: 0; }
.sidebar-menu { list-style: none; padding: 0; margin: 0; }
.sidebar-menu li a { display: flex; align-items: center; padding: 12px 24px; color: #2d3748; text-decoration: none; font-size: 15px; font-weight: 500; }
.sidebar-menu li a:hover, .sidebar-menu li.active a { background-color: #f3f4f6; color: #5b21b6; border-left: 4px solid #5b21b6; }
.sidebar-content-area { flex-grow: 1; padding: 30px; }
.welcome-title { font-size: 28px; font-weight: 700; margin-bottom: 25px; color: #1a202c; }
.pearson-assigned-card { background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
.section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1a202c; }
.classes-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; border-top: 1px solid #edf2f7; border-bottom: 1px solid #edf2f7; padding: 20px 0; }
.class-item { padding-right: 15px; border-right: 1px solid #edf2f7; }
.class-code { color: #5b21b6; font-weight: 700; font-size: 16px; text-decoration: none; }
.class-desc { font-size: 12px; color: #718096; margin: 5px 0 10px 0; }
</style>

<div class="pearson-main-container">
    <!-- Sidebar -->
    <div class="pearson-sidebar">
        <ul class="sidebar-menu">
            <li class="active"><a href="#">Home</a></li>
            <li><a href="#">Students</a></li>
            <li><a href="#">Classes</a></li>
            <li><a href="#">My profile</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="pearson-content-area">
        <h2 class="welcome-title">Welcome <?php echo fullname($USER); ?></h2>
        
        <!-- Assigned Classes Section (Dynamic from Moodle DB) -->
        <div class="pearson-assigned-card">
            <h3 class="section-title">Assigned Classes</h3>
            <div class="classes-grid">
                <?php 
                if (!empty($my_courses)) {
                    foreach ($my_courses as $course) {
                        echo '<div class="class-item">';
                        echo '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'" class="class-code">'.format_string($course->shortname).'</a>';
                        echo '<p class="class-desc">'.format_string($course->fullname).'</p>';
                        echo '<span class="class-date">📅 Active Course</span>';
                        echo '</div>';
                    }
                } else {
                    echo '<p>No classes assigned currently.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();