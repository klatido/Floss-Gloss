<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = $_POST['action'] ?? '';
    $appointment_id = intval($_POST['appointment_id']);
    $staff_id = 1; 

    switch ($action) {
        case 'approve_appointment':
            $query = "UPDATE appointments 
                      SET status = 'approved', approved_by_staff_id = ?, approved_at = NOW() 
                      WHERE appointment_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $staff_id, $appointment_id);
            
            if ($stmt->execute()) {
                $log_query = "INSERT INTO appointment_action_logs (appointment_id, action_type, acted_by_user_id, new_status) 
                              VALUES (?, 'approved', ?, 'approved')";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("ii", $appointment_id, $staff_id);
                $log_stmt->execute();

                header("Location: ../admin/admin-dashboard.php?success=approved");
            } else {
                echo "Error updating record: " . $conn->error;
            }
            exit;

        case 'reject_appointment':
            $query = "UPDATE appointments SET status = 'rejected' WHERE appointment_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $appointment_id);
            
            if ($stmt->execute()) {
                $log_query = "INSERT INTO appointment_action_logs (appointment_id, action_type, acted_by_user_id, new_status) 
                              VALUES (?, 'rejected', ?, 'rejected')";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("ii", $appointment_id, $staff_id);
                $log_stmt->execute();

                header("Location: ../admin/admin-dashboard.php?success=rejected");
            } else {
                echo "Error updating record: " . $conn->error;
            }
            exit;

        default:
            header("Location: ../admin/admin-dashboard.php");
            exit;
    }
} else {
    header("Location: ../admin/admin-dashboard.php");
    exit;
}
?>
