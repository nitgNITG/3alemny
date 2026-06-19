<?php
/**
 * English language strings for local_livesessions.
 *
 * @package    local_livesessions
 * @copyright  2024 3alemny.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin
$string['pluginname'] = 'Live Sessions';

// Page titles / headings
$string['createsession']   = 'Create Live Session';
$string['editsession']     = 'Edit Live Session';
$string['sessiondetails']  = 'Session Details';
$string['backtosessions']  = 'Back to Sessions';
$string['joinsession']     = 'Join Live Session';

// Form fields
$string['title']               = 'Session Title';
$string['description']         = 'Description';
$string['provider']            = 'Provider';
$string['provider_meeting_id'] = 'Provider Meeting ID';
$string['join_url']            = 'Student Join URL';
$string['host_url']            = 'Host / Teacher URL';
$string['starttime']           = 'Start Time';
$string['endtime']             = 'End Time';
$string['duration']            = 'Duration';
$string['minutes']             = 'minutes';
$string['capacity']            = 'Capacity';
$string['capacity_help']       = 'Maximum number of students. Enter 0 for unlimited.';
$string['teacher']             = 'Teacher';
$string['teacherid']           = 'Teacher User ID';
$string['schedule']            = 'Schedule';
$string['updatesession']       = 'Update Session';

// Status labels
$string['status']     = 'Status';
$string['all']        = 'All';
$string['scheduled']  = 'Scheduled';
$string['live']       = 'Live';
$string['completed']  = 'Completed';
$string['cancelled']  = 'Cancelled';
$string['pending']    = 'Pending';
$string['attended']   = 'Attended';
$string['absent']     = 'Absent';
$string['partial']    = 'Partial';

// Actions
$string['actions']  = 'Actions';
$string['view']     = 'View';
$string['edit']     = 'Edit';
$string['cancel']   = 'Cancel';
$string['join']     = 'Join';

// Messages
$string['sessioncreated']          = 'Session created successfully.';
$string['sessionupdated']          = 'Session updated successfully.';
$string['sessioncancelled']        = 'Session cancelled.';
$string['nosessions']              = 'No sessions found.';
$string['confirmsessioncancel']    = 'Are you sure you want to cancel this session?';
$string['sessionnotfound']         = 'Session not found.';
$string['cannotedicompletdsession']= 'Cannot edit a completed session.';
$string['cannotedircancelledsession'] = 'Cannot edit a cancelled session.';
$string['cannotcancelcompleted']   = 'Cannot cancel a completed session.';
$string['cannotstartinvalidstatus']= 'Session cannot be started from its current status.';
$string['cannotcompleteinvalidstatus'] = 'Session cannot be completed from its current status.';

// Validation
$string['missingcourseid']  = 'Course ID is required.';
$string['missingtitle']     = 'Session title is required.';
$string['missingtimes']     = 'Start time and end time are required.';
$string['endbeforestart']   = 'End time must be after start time.';
$string['invalidprovider']  = 'Invalid provider selected.';

// Attendance
$string['attendance']          = 'Attendance';
$string['noattendance']        = 'No attendance records yet.';
$string['student']             = 'Student';
$string['jointime']            = 'Join Time';
$string['leavetime']           = 'Leave Time';
$string['durationattended']    = 'Duration';
$string['attendancepercent']   = 'Attendance %';
$string['attendancestatus']    = 'Status';
$string['recordingaccess']     = 'Recording Access';

// Recording
$string['recording']              = 'Session Recording';
$string['recordingaccessdenied']  = 'You did not meet the attendance threshold to access this recording.';
$string['nosessioncredits']       = 'You have no remaining session credits.';
$string['creditconsumed']         = 'Session credit deducted successfully.';

// Events
$string['event_sessioncreated']   = 'Live session created';
$string['event_sessionupdated']   = 'Live session updated';
$string['event_sessioncancelled'] = 'Live session cancelled';

// Tasks
$string['task_processrecording']  = 'Process live session recording';

// Webhook
$string['invalidwebhooktoken']    = 'Invalid webhook token.';

// Feature 1.2 additions
$string['joinsession']               = 'Join Live Session';
$string['readytojoin']               = 'Your session is ready. Click the button below to join.';
$string['welcomeback']               = 'Welcome back! Re-joining your session.';
$string['openprovider']              = 'Open Session';
$string['joininstructions']          = 'The session has opened in a new tab. When you are done, click the button below to record your attendance.';
$string['sessionfinished']           = 'I have finished the session';
$string['attendancerecorded']        = 'Your attendance has been recorded. Thank you!';
$string['sessionnotstarted']         = 'This session has not started yet. You can join up to 15 minutes early.';
$string['sessioncompleted']          = 'This session has already ended.';
$string['notenrolled']               = 'You are not enrolled in this course.';
$string['sessionfull']               = 'This session has reached its maximum capacity.';
$string['nosessioncredits']          = 'You have no remaining session credits in your package.';
$string['invalidjointoken']          = 'Invalid or missing join token.';
$string['jointokenalreadyused']      = 'This join link has already been used.';
$string['jointokenexpired']          = 'This join link has expired. Please return to the session page and try again.';
$string['attendancereport']          = 'Attendance Report';
$string['attendancesegments']        = 'Attendance Segments';
$string['attendancethresholdnotice'] = 'Students must attend {$a}% or more of the session to gain recording access.';
$string['joincount']                 = 'Joins';
$string['viewsegments']              = 'Segments';
$string['nosegments']                = 'No detailed segments recorded.';
$string['backtosession']             = 'Back to Session';
$string['backtoattendance']          = 'Back to Attendance';
$string['total']                     = 'Total';
$string['downloadcsv']               = 'Download CSV';
$string['grantaccess']               = 'Grant Access';
$string['accessgranted']             = 'Recording access granted.';
$string['event_studentjoined']       = 'Student joined live session';
$string['event_studentleft']         = 'Student left live session';
$string['sessioncancelled']          = 'Session cancelled.';

// Settings
$string['settings']                   = 'Live Sessions Settings';
$string['attendance_threshold']       = 'Attendance Threshold (%)';
$string['attendance_threshold_desc']  = 'Minimum attendance percentage for a student to be marked as "attended" and gain recording access.';
$string['webhook_secret']             = 'Webhook Secret';
$string['webhook_secret_desc']        = 'Shared secret for validating incoming webhooks from video providers.';
$string['default_provider']           = 'Default Provider';
$string['default_provider_desc']      = 'Default video conferencing provider for new sessions.';
$string['credit_mode']                = 'Recording Access Mode';
$string['credit_mode_desc']           = 'Attendance: recording access granted automatically if student attended ≥ threshold. Credit: student must spend one package credit to watch.';
$string['credit_mode_attendance']     = 'Attendance-based (recommended)';
$string['credit_mode_credit']         = 'Credit-based (deduct from package)';
$string['attendancesummary']          = '{$a->attended} of {$a->total} students attended.';

// Feature 1.3 — Attendance Engine
$string['finaliseattendance']         = 'Finalise Attendance';
$string['finalisenow']                = 'Finalise Now';
$string['finalisedat']                = 'Finalised At';
$string['notfinalised']               = 'Not yet finalised';
$string['finalisedok']                = 'Finalised: {$a->attended} attended, {$a->partial} partial, {$a->absent} absent out of {$a->total} students.';
$string['recalculate']                = 'Recalculate';
$string['recalcok']                   = 'Attendance recalculated for this student.';
$string['viewlog']                    = 'Log';
$string['studentattendance']          = 'Student Attendance';
$string['finalisationhistory']        = 'Finalisation History';
$string['triggeredby']                = 'Triggered By';
$string['cron']                       = 'System (cron)';
$string['system']                     = 'System';
$string['time']                       = 'Time';
$string['attendancethreshold']        = 'Attendance Threshold';
$string['attauditlog']                = 'Attendance Audit Log';
$string['nologentries']               = 'No audit log entries found.';
$string['changedby']                  = 'Changed By';
$string['reason']                     = 'Reason';
$string['prevstatus']                 = 'Previous Status';
$string['newstatus']                  = 'New Status';
$string['prevpercent']                = 'Previous %';
$string['newpercent']                 = 'New %';
$string['prevduration']               = 'Previous Duration';
$string['newduration']                = 'New Duration';
$string['recaccess']                  = 'Recording Access Change';
$string['backtofinalisepage']         = 'Back to Finalise Page';
$string['task_finaliseattendance']    = 'Finalise live session attendance';
$string['task_expiresubscriptions']   = 'Expire stale session package subscriptions';
$string['task_cleanuptokens']         = 'Clean up expired join tokens';
$string['task_autosstartsessions']    = 'Auto-start and auto-complete live sessions';
$string['finalise_grace_minutes']     = 'Finalisation Grace Period (minutes)';
$string['finalise_grace_minutes_desc']= 'How many minutes after a session ends before cron automatically finalises attendance. Default: 5.';

// Feature 2.1 — Recording Detection
$string['recordingqueue']            = 'Recording Queue';
$string['norecordingsjobs']          = 'No recording jobs found.';
$string['sourceprovider']            = 'Source Provider';
$string['retrycount']                = 'Retries';
$string['lasterror']                 = 'Last Error';
$string['timecreated']               = 'Created';
$string['retry']                     = 'Retry';
$string['preview']                   = 'Preview';
$string['recentwebhooks']            = 'Recent Webhooks';
$string['recauditlog']               = 'Recording Audit Log';
$string['backtoqueue']               = 'Back to Queue';
$string['session']                   = 'Session';
$string['recordingretryqueued']      = 'Recording retry has been queued.';
$string['nodownloadurl']             = 'Recording has no download URL — cannot process.';
$string['task_retryrecordings']      = 'Retry stalled recording uploads';

// Recording provider settings
$string['upload_provider']           = 'Recording Upload Provider';
$string['upload_provider_desc']      = 'Where processed recordings are uploaded for student playback.';
$string['webhook_secret_zoom']       = 'Zoom Webhook Secret';
$string['webhook_secret_zoom_desc']  = 'From Zoom App → Features → Event Subscriptions → Secret Token.';
$string['webhook_secret_100ms']      = '100ms Webhook Secret';
$string['webhook_secret_100ms_desc'] = 'From 100ms Dashboard → Developer → Webhooks → Secret.';
$string['webhook_secret_agora']      = 'Agora Webhook Token';
$string['webhook_secret_agora_desc'] = 'Shared token appended as ?token= to your Agora callback URL.';
$string['webhook_secret_bigbluebutton'] = 'BigBlueButton Shared Secret';
$string['webhook_secret_bigbluebutton_desc'] = 'From /etc/bigbluebutton/bbb-web.properties → securitySalt.';
$string['bbb_server_url']            = 'BigBlueButton Server URL';
$string['bbb_server_url_desc']       = 'Base URL of your BBB server, e.g. https://bbb.example.com.';

// Feature 2.2 — Bunny Stream settings
$string['bunny_library_id']           = 'Bunny Library ID';
$string['bunny_library_id_desc']      = 'Numeric library ID from Bunny Stream → Library → Settings.';
$string['bunny_library_api_key']      = 'Bunny Library API Key';
$string['bunny_library_api_key_desc'] = 'API key for this library (not the account-level key). Found under Library → API Access.';
$string['bunny_cdn_hostname']         = 'Bunny CDN Hostname';
$string['bunny_cdn_hostname_desc']    = 'Pull zone hostname for video delivery, e.g. vz-abc123.b-cdn.net (no https://).';
$string['bunny_token_auth_key']       = 'Bunny Token Authentication Key';
$string['bunny_token_auth_key_desc']  = 'From Bunny Stream → Library → Security → Token Authentication Key. Required for signed embed URLs.';
$string['bunny_token_ttl']            = 'Signed URL TTL (seconds)';
$string['bunny_token_ttl_desc']       = 'How long each signed playback URL remains valid. Default: 14400 (4 hours).';
$string['bunny_collection_id']        = 'Bunny Collection ID (optional)';
$string['bunny_collection_id_desc']   = 'If set, uploaded recordings are placed into this Bunny collection.';
$string['bunny_allowed_referer']      = 'Allowed Referer Domain (optional)';
$string['bunny_allowed_referer_desc'] = 'Lock playback to this domain, e.g. https://yoursite.com. Leave blank to disable referer locking.';
$string['bunny_encode_timeout']       = 'Encoding Timeout (seconds)';
$string['bunny_encode_timeout_desc']  = 'Maximum time to wait for Bunny to finish encoding before declaring the upload failed. Default: 3600 (1 hour).';
$string['webhook_secret_bunny']       = 'Bunny Webhook Secret';
$string['webhook_secret_bunny_desc']  = 'Security key from Bunny Stream → Library → Webhooks, used to verify encoding-complete callbacks.';

// Feature 2.2 — Player page
// Phase 3 — Package System
$string['packages']                  = 'Session Packages';
$string['packagename']               = 'Package Name';
$string['createpackage']             = 'Create Package';
$string['editpackage']               = 'Edit Package';
$string['savepackage']               = 'Save Package';
$string['packagecreated']            = 'Package created successfully.';
$string['packageupdated']            = 'Package updated successfully.';
$string['packagearchived']           = 'Package archived.';
$string['nopackages']                = 'No packages found.';
$string['sessions_count']            = 'Sessions Included';
$string['validity_days']             = 'Validity (days)';
$string['validity_days_desc']        = 'Number of days the subscription is valid after assignment. Enter 0 for no expiry.';
$string['price']                     = 'Price';
$string['currency']                  = 'Currency';
$string['subscribers']               = 'Subscribers';
$string['archive']                   = 'Archive';
$string['activate']                  = 'Activate';
$string['deactivate']                = 'Deactivate';
$string['days']                      = 'days';
$string['nolimit']                   = 'No limit';
$string['confirmarchivepackage']     = 'Are you sure you want to archive this package? This cannot be undone.';
$string['cannotarchivepackagewithsubs'] = 'Cannot archive a package with active subscriptions.';
$string['packagenotactive']          = 'This package is not currently active.';
$string['missingpackagename']        = 'Package name is required.';
$string['invalidsessionscount']      = 'Sessions count must be at least 1.';

// Feature 3.2 — Student Subscriptions
$string['packagesubscriptions']      = 'Package Subscriptions';
$string['assignuserid']              = 'Assign to User ID:';
$string['assignpackage']             = 'Assign';
$string['packageassigned']           = 'Package assigned successfully.';
$string['subscriptioncancelled']     = 'Subscription cancelled.';
$string['nosubscriptions']           = 'No subscriptions found for this package.';
$string['used']                      = 'Used';
$string['remaining']                 = 'Remaining';
$string['expiry']                    = 'Expiry';
$string['confirmcancelsub']          = 'Are you sure you want to cancel this subscription?';
$string['mystudentpackages']         = 'My Session Credits';

// Feature 3.3 — Credit Consumption
$string['creditlog']                 = 'Credit Audit Log';
$string['nocreditlog']               = 'No credit log entries found.';
$string['action']                    = 'Action';
$string['creditsbefore']             = 'Credits Before';
$string['creditsafter']              = 'Credits After';

// Feature 2.4 — Auto activity creation
$string['auto_create_activity']       = 'Auto-create Course Activity on Recording Ready';
$string['auto_create_activity_desc']  = 'When enabled, a URL resource activity is automatically added to the course when a recording finishes processing. Students can find the recording in the course content tree.';
$string['auto_activity_section']      = 'Activity Section Number';
$string['auto_activity_section_desc'] = 'Which course section to place the auto-created recording activity in. 0 = General (top section).';

// Feature 2.3 — VdoCipher settings
$string['vdocipher_api_secret']           = 'VdoCipher API Secret';
$string['vdocipher_api_secret_desc']      = 'API secret from VdoCipher Dashboard → API Keys.';
$string['vdocipher_folder_id']            = 'VdoCipher Folder ID (optional)';
$string['vdocipher_folder_id_desc']       = 'Move uploaded recordings into this VdoCipher folder.';
$string['vdocipher_encode_timeout']       = 'VdoCipher Encoding Timeout (seconds)';
$string['vdocipher_encode_timeout_desc']  = 'Maximum time to wait for VdoCipher encoding. Default: 3600.';
$string['vdocipher_otp_ttl']              = 'VdoCipher OTP TTL (seconds)';
$string['vdocipher_otp_ttl_desc']         = 'How long each OTP playback token is valid. Default: 300 (5 minutes).';

$string['norecordingready']          = 'The recording is not yet available. Please check back later.';
$string['recordingplayer']           = 'Recording Player';
$string['recordingplayerdesc']       = 'Click the button above to open the recording in a secure player.';

// Feature 4.1 / 4.2 — Recording Access
$string['unlockrecording']           = 'Unlock Recording';
$string['unlockrecordingconfirm']    = 'Unlock the recording for "{$a->title}"? You have {$a->remaining} credit(s) remaining. One credit will be deducted.';
$string['unlockrecordingbtn']        = 'Yes, Unlock Recording';
$string['recordingaccessgranted']    = 'Recording unlocked! You can now watch the recording.';
$string['creditaccessnotenabled']    = 'Credit-based recording access is not enabled on this site.';
$string['creditcost1']               = 'Cost: 1 session credit';

// Feature 5.1 / 5.2 / 5.3 — Mobile API strings
$string['mobile_sessionlist']        = 'Session list';
$string['mobile_sessiondetail']      = 'Session detail';
$string['mobile_joinsession']        = 'Join session';
$string['mobile_leavesession']       = 'Leave session';
$string['mobile_recordingtoken']     = 'Get recording token';
$string['mobile_mypackages']         = 'My session packages';

// Feature 6.1 — Device limits
$string['devicelimitexceeded']       = 'You have reached the maximum number of active devices allowed for this session ({$a} device(s)).';
$string['deviceregistered']          = 'Device registered for session.';
$string['devicereleased']            = 'Device released.';
$string['max_devices_per_session']   = 'Max Concurrent Devices';
$string['max_devices_per_session_desc'] = 'Maximum number of devices a student may use simultaneously in one session. Default: 2. Set to 0 to disable the limit.';

// Feature 6.2 — Watermarks
$string['watermark_mode']            = 'Video Watermark Mode';
$string['watermark_mode_desc']       = 'Overlay the student\'s name and/or phone number on recordings to deter screen recording. Requires VdoCipher annotation support.';
$string['watermark_mode_off']        = 'Disabled';
$string['watermark_mode_name']       = 'Student name only';
$string['watermark_mode_namephone']  = 'Name + phone number';

// Feature 7 — Admin Dashboard / Reports
$string['dashboard']                 = 'Live Sessions Dashboard';
$string['reports']                   = 'Package & Revenue Reports';
$string['totalsessions']             = 'Total Sessions';
$string['livesessionscount']         = 'Live Now';
$string['totalstudents']             = 'Total Students';
$string['avgattendance']             = 'Avg Attendance %';
$string['recordingsready']           = 'Recordings Ready';
$string['totalpackages']             = 'Total Packages';
$string['totalsubscriptions']        = 'Total Subscriptions';
$string['totalcreditsused']          = 'Credits Consumed';
$string['recentactivity']            = 'Recent Activity';
$string['coursename']                = 'Course';
$string['sessioncount']              = 'Sessions';
$string['attendancerate']            = 'Attendance Rate';
$string['revenuebypackage']          = 'Revenue by Package';
$string['creditsgranted']            = 'Credits Granted';
$string['creditsconsumed']           = 'Credits Consumed';
$string['creditsremaining']          = 'Credits Remaining';
$string['exportcsv']                 = 'Export CSV';
$string['nodashboarddata']           = 'No session data available yet.';
$string['noreportdata']              = 'No package data found for the selected period.';
$string['daterange']                 = 'Date Range';
$string['datefrom']                  = 'From';
$string['dateto']                    = 'To';
$string['apply']                     = 'Apply';
$string['backtodashboard']           = 'Back to Dashboard';

// Start session strings
$string['startsession']            = 'Start Session';
$string['confirmstartsession']     = 'This will mark the session as LIVE and open the host room. Continue?';
$string['sessionstarted']          = 'Session is now live.';
$string['cannotstartcompleted']    = 'Cannot start a session that is already completed or cancelled.';
$string['hostroom']                = 'Host Room';

// Embedded room strings
$string['enterroom']              = 'Enter Room';
$string['sessionnotlive']         = 'This session has not started yet.';
$string['zoom_sdk_heading']       = 'Zoom Embedded Meeting (SDK)';
$string['zoom_sdk_heading_desc']  = 'Required for sessions to open inside Moodle. Get these from your Zoom Marketplace app → App Credentials → Meeting SDK.';
$string['zoom_sdk_key']           = 'Meeting SDK Key';
$string['zoom_sdk_key_desc']      = 'Your Zoom Meeting SDK Key (SDK Key, not API Key).';
$string['zoom_sdk_secret']        = 'Meeting SDK Secret';
$string['zoom_sdk_secret_desc']   = 'Your Zoom Meeting SDK Secret.';

// ---------------------------------------------------------------
// 1-to-1 Private Session strings
// ---------------------------------------------------------------
$string['request_session']            = 'Request Private Session';
$string['my_sessions']                = 'My Sessions';
$string['pending_requests']           = 'Session Requests';
$string['new_request']                = 'Request New Session';
$string['no_sessions']                = 'You have no private session requests yet.';
$string['no_pending']                 = 'No pending requests at the moment.';
$string['pending_count']              = 'You have {$a->n} pending request(s).';
$string['select_teacher']             = 'Select Teacher';
$string['session_date']               = 'Preferred Date';
$string['session_time']               = 'Preferred Time';
$string['note_optional']              = 'Note (optional)';
$string['note_placeholder']           = 'e.g. I need help with Chapter 3 exercises';
$string['note_help']                  = 'Briefly describe what you would like to discuss in the session.';
$string['send_request']               = 'Send Request';
$string['approve']                    = 'Approve';
$string['reject']                     = 'Reject';
$string['reject_reason']              = 'Rejection reason';
$string['reject_reason_placeholder']  = 'Optional reason for rejection';
$string['no_reason_given']            = 'No reason provided.';
$string['confirm_approve']            = 'Approve this request? A Zoom meeting will be created automatically.';
$string['starts_in']                  = 'Starts in {$a->h}h {$a->m}m';
$string['no_teachers_in_course']      = 'No teachers found in this course.';
$string['invalid_teacher']            = 'Please select a valid teacher from this course.';
$string['request_sent']               = 'Your session request has been sent. The teacher will review it shortly.';
$string['request_approved']           = 'Request approved! Zoom meeting created and student notified.';
$string['request_rejected']           = 'Request rejected. The student has been notified.';
$string['status_none']                = 'N/A';
$string['status_pending']             = 'Pending';
$string['status_approved']            = 'Approved';
$string['status_rejected']            = 'Rejected';
$string['invalidrequest']             = 'This request cannot be modified in its current state.';
$string['zoom_not_configured']        = 'Zoom API credentials are not configured. Please set them in plugin settings.';

// Zoom S2S OAuth settings
$string['zoom_s2s_heading']           = 'Zoom Server-to-Server OAuth (Auto Meeting Creation)';
$string['zoom_s2s_heading_desc']      = 'Used to automatically create Zoom meetings when a teacher approves a private session. Get these from Zoom Marketplace → Server-to-Server OAuth app.';
$string['zoom_api_account_id']        = 'Account ID';
$string['zoom_api_account_id_desc']   = 'Your Zoom Account ID from the Server-to-Server OAuth app.';
$string['zoom_api_client_id']         = 'Client ID';
$string['zoom_api_client_id_desc']    = 'Your Zoom Client ID from the Server-to-Server OAuth app.';
$string['zoom_api_client_secret']     = 'Client Secret';
$string['zoom_api_client_secret_desc']= 'Your Zoom Client Secret from the Server-to-Server OAuth app.';

// Notification strings
$string['notify_new_request_subject'] = 'New private session request from {$a->student}';
$string['notify_new_request_body']    = '{$a->student} has requested a private session on {$a->time}. Note: {$a->note}';
$string['notify_approved_subject']    = 'Your private session has been approved';
$string['notify_approved_body']       = 'Great news! {$a->teacher} approved your session for {$a->time}. Join here: {$a->join_url}';
$string['notify_rejected_subject']    = 'Your session request was not approved';
$string['notify_rejected_body']       = '{$a->teacher} was unable to accept your session request. Reason: {$a->reason}';

$string['zoom_token_error']           = 'Failed to obtain Zoom access token. Check your API credentials.';
$string['zoom_create_meeting_error']  = 'Failed to create Zoom meeting via API.';
