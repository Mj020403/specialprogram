<?php

require_once dirname(__DIR__, 4) . '/app/includes/helpers/core.php';
require_once dirname(__DIR__, 4) . '/app/includes/helpers/operations.php';

function executive_action_summary(mysqli $conn): array {
    ensure_decision_support_schema($conn);
    $overdueSql = "SELECT h.household_id, h.household_head_name, h.household_code, b.barangay_name,
                          lm.last_monitoring_date,
                          COALESCE(q.qualification_status, 'For Validation') AS qualification_status,
                          COALESCE(q.score, 0) AS score
                   FROM households h
                   LEFT JOIN barangays b ON b.barangay_id = h.barangay_id
                   LEFT JOIN (
                       SELECT household_id, MAX(monitoring_date) AS last_monitoring_date
                       FROM monitoring_visits
                       GROUP BY household_id
                   ) lm ON lm.household_id = h.household_id
                   LEFT JOIN household_qualification q ON q.household_id = h.household_id
                   WHERE COALESCE(h.record_status,'active')='active' AND (lm.last_monitoring_date IS NULL
                      OR lm.last_monitoring_date < DATE_SUB(CURDATE(), INTERVAL 180 DAY))
                   ORDER BY CASE WHEN lm.last_monitoring_date IS NULL THEN 0 ELSE 1 END ASC,
                            lm.last_monitoring_date ASC,
                            score ASC,
                            h.household_id DESC
                   LIMIT 12";
    return [
        'overdue_monitoring' => fetch_all_assoc($conn, $overdueSql),
        'followup_due' => table_exists($conn, 'assistance_records') ? fetch_all_assoc($conn, "SELECT a.assistance_id,a.household_id,h.household_head_name,h.household_code,b.barangay_name,a.assistance_type,a.assistance_status,a.next_followup_date,DATEDIFF(a.next_followup_date, CURDATE()) AS days_remaining FROM assistance_records a JOIN households h ON h.household_id=a.household_id LEFT JOIN barangays b ON b.barangay_id=h.barangay_id WHERE COALESCE(h.record_status,'active')='active' AND a.next_followup_date IS NOT NULL AND a.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND a.assistance_status NOT IN ('Cancelled','Completed') ORDER BY a.next_followup_date ASC, a.assistance_id DESC LIMIT 12") : [],
        'interview_backlog' => fetch_all_assoc($conn, "SELECT h.household_id,h.household_head_name,h.household_code,b.barangay_name,COALESCE(q.qualification_status,'For Validation') AS qualification_status,COALESCE(q.score,0) AS score FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE COALESCE(h.record_status,'active')='active' AND NOT EXISTS (SELECT 1 FROM interviews i WHERE i.household_id=h.household_id AND i.status='Completed') ORDER BY score ASC, h.household_id DESC LIMIT 12"),
        'high_risk' => fetch_all_assoc($conn, "SELECT h.household_id,h.household_head_name,h.household_code,b.barangay_name,COALESCE(q.qualification_status,'For Validation') AS qualification_status,COALESCE(q.score,0) AS score,q.explanation FROM households h LEFT JOIN barangays b ON b.barangay_id=h.barangay_id LEFT JOIN household_qualification q ON q.household_id=h.household_id WHERE COALESCE(h.record_status,'active')='active' AND COALESCE(q.qualification_status,'For Validation') IN ('High Risk','Needs Support','For Validation') ORDER BY score ASC, h.household_id DESC LIMIT 12"),
    ];
}

