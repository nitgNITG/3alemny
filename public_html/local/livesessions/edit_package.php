<?php
/**
 * Create / edit a session package — Feature 3.1.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_livesessions\package_manager;

$id = optional_param('id', 0, PARAM_INT); // 0 = create new

require_login();
require_capability('local/livesessions:manageSessions', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/livesessions/edit_package.php', ['id' => $id]);
$PAGE->set_pagelayout('admin');

$is_edit = ($id > 0);
$pkg     = $is_edit ? package_manager::get_package($id) : null;

$PAGE->set_title($is_edit ? get_string('editpackage', 'local_livesessions') : get_string('createpackage', 'local_livesessions'));
$PAGE->set_heading($is_edit ? get_string('editpackage', 'local_livesessions') : get_string('createpackage', 'local_livesessions'));

// ---- Handle form submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $name           = required_param('name',           PARAM_TEXT);
    $description    = optional_param('description',    '', PARAM_RAW);
    $sessions_count = required_param('sessions_count', PARAM_INT);
    $price          = optional_param('price',          0.0, PARAM_FLOAT);
    $currency       = optional_param('currency',       'USD', PARAM_ALPHA);
    $validity_days  = optional_param('validity_days',  0, PARAM_INT);
    $courseid       = optional_param('courseid',       0, PARAM_INT);

    try {
        if ($is_edit) {
            package_manager::update_package($id, compact(
                'name','description','sessions_count','price','currency','validity_days','courseid'
            ));
            \core\notification::success(get_string('packageupdated', 'local_livesessions'));
        } else {
            package_manager::create_package(
                $name, $description, $sessions_count, $price, $currency, $validity_days, $courseid
            );
            \core\notification::success(get_string('packagecreated', 'local_livesessions'));
        }
        redirect(new moodle_url('/local/livesessions/packages.php'));
    } catch (\moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($PAGE->title);

// ---- Form ----
$sesskey_field = html_writer::empty_tag('input', ['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);
$id_field      = html_writer::empty_tag('input', ['type'=>'hidden','name'=>'id','value'=>$id]);

$name_val     = $pkg->name           ?? '';
$desc_val     = $pkg->description    ?? '';
$count_val    = $pkg->sessions_count ?? 10;
$price_val    = $pkg->price          ?? 0;
$currency_val = $pkg->currency       ?? 'USD';
$days_val     = $pkg->validity_days  ?? 0;
$course_val   = $pkg->courseid       ?? 0;

$form_html = <<<HTML
<form method="post" action="">
{$sesskey_field}{$id_field}
<div class="form-group row">
  <label class="col-sm-3 col-form-label">{$OUTPUT->str('packagename','local_livesessions')}</label>
  <div class="col-sm-9"><input type="text" name="name" class="form-control" value="{$name_val}" required maxlength="255"></div>
</div>
<div class="form-group row">
  <label class="col-sm-3 col-form-label">{$OUTPUT->str('description','local_livesessions')}</label>
  <div class="col-sm-9"><textarea name="description" class="form-control" rows="3">{$desc_val}</textarea></div>
</div>
<div class="form-group row">
  <label class="col-sm-3 col-form-label">{$OUTPUT->str('sessions_count','local_livesessions')}</label>
  <div class="col-sm-9"><input type="number" name="sessions_count" class="form-control" value="{$count_val}" min="1" required></div>
</div>
<div class="form-group row">
  <label class="col-sm-3 col-form-label">{$OUTPUT->str('price','local_livesessions')}</label>
  <div class="col-sm-6"><input type="number" name="price" class="form-control" value="{$price_val}" min="0" step="0.01"></div>
  <div class="col-sm-3"><input type="text" name="currency" class="form-control" value="{$currency_val}" maxlength="3" placeholder="USD"></div>
</div>
<div class="form-group row">
  <label class="col-sm-3 col-form-label">{$OUTPUT->str('validity_days','local_livesessions')}</label>
  <div class="col-sm-9"><input type="number" name="validity_days" class="form-control" value="{$days_val}" min="0">
  <small class="form-text text-muted">{$OUTPUT->str('validity_days_desc','local_livesessions')}</small></div>
</div>
<div class="form-group row mt-3">
  <div class="col-sm-9 offset-sm-3">
    <button type="submit" class="btn btn-primary">{$OUTPUT->str('savepackage','local_livesessions')}</button>
    <a href="/local/livesessions/packages.php" class="btn btn-secondary ml-2">{$OUTPUT->str('cancel','local_livesessions')}</a>
  </div>
</div>
</form>
HTML;

echo html_writer::div($form_html, 'card card-body');
echo $OUTPUT->footer();
