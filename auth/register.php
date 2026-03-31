<?php
include("../config/database.php");

$message = "";
$is_success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["first_name"] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $sex = trim($_POST["sex"] ?? "");
    $birth_date = trim($_POST["birth_date"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $emergency_contact_name = trim($_POST["emergency_contact_name"] ?? "");
    $emergency_contact_phone = trim($_POST["emergency_contact_phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Basic validation
    if (
        empty($first_name) ||
        empty($last_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!empty($sex) && !in_array($sex, ["male", "female", "prefer_not_to_say"])) {
        $message = "Invalid sex value.";
    } elseif (!empty($birth_date) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $birth_date)) {
        $message = "Invalid birth date format.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);

        if (!$check_stmt) {
            $message = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($check_stmt, "s", $email);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);

            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $message = "Email already exists.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Start transaction
                mysqli_begin_transaction($conn);

                try {
                    // 1. Insert into users
                    $user_sql = "INSERT INTO users (role, email, phone, password_hash, account_status, email_verified)
                                 VALUES ('patient', ?, ?, ?, 'active', 0)";
                    $user_stmt = mysqli_prepare($conn, $user_sql);

                    if (!$user_stmt) {
                        throw new Exception("Failed to prepare user insert.");
                    }

                    mysqli_stmt_bind_param($user_stmt, "sss", $email, $phone, $password_hash);

                    if (!mysqli_stmt_execute($user_stmt)) {
                        throw new Exception("Failed to insert user.");
                    }

                    $user_id = mysqli_insert_id($conn);

                    // Convert empty strings to NULL for optional fields
                    $middle_name_db = $middle_name !== "" ? $middle_name : null;
                    $sex_db = $sex !== "" ? $sex : null;
                    $birth_date_db = $birth_date !== "" ? $birth_date : null;
                    $address_db = $address !== "" ? $address : null;
                    $emergency_contact_name_db = $emergency_contact_name !== "" ? $emergency_contact_name : null;
                    $emergency_contact_phone_db = $emergency_contact_phone !== "" ? $emergency_contact_phone : null;

                    // 2. Insert into patient_profiles
                    $profile_sql = "INSERT INTO patient_profiles
                                    (user_id, first_name, middle_name, last_name, sex, birth_date, address, emergency_contact_name, emergency_contact_phone)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $profile_stmt = mysqli_prepare($conn, $profile_sql);

                    if (!$profile_stmt) {
                        throw new Exception("Failed to prepare patient profile insert.");
                    }

                    mysqli_stmt_bind_param(
                        $profile_stmt,
                        "issssssss",
                        $user_id,
                        $first_name,
                        $middle_name_db,
                        $last_name,
                        $sex_db,
                        $birth_date_db,
                        $address_db,
                        $emergency_contact_name_db,
                        $emergency_contact_phone_db
                    );

                    if (!mysqli_stmt_execute($profile_stmt)) {
                        throw new Exception("Failed to insert patient profile.");
                    }

                    mysqli_commit($conn);
                    $is_success = true;
                    $message = "Registration successful. You can now log in.";

                    // Optional: clear form values after success
                    $first_name = $middle_name = $last_name = $email = $phone = $sex = $birth_date = $address = "";
                    $emergency_contact_name = $emergency_contact_phone = "";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "Registration failed. " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Floss & Gloss Dental</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #05264d, #0b6b67, #1b4fa3);
        }

        .card {
            width: 100%;
            max-width: 720px;
            background: #ffffff;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.12);
        }

        .logo-box {
            width: 84px;
            height: 84px;
            margin: 0 auto 20px;
            border-radius: 22px;
            background: linear-gradient(135deg, #00b7c6, #1769ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 38px;
        }

        h1 {
            text-align: center;
            margin: 0 0 8px;
            font-size: 34px;
            color: #111827;
        }

        .subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 28px;
        }

        .message {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 14px;
            background: #f3f4f6;
            font-size: 15px;
            outline: none;
        }

        input:focus,
        select:focus,
        textarea:focus {
            box-shadow: 0 0 0 2px #18a8b5;
            background: #ffffff;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        .btn {
            width: 100%;
            margin-top: 10px;
            border: none;
            background: #0ea5a0;
            color: white;
            padding: 16px;
            border-radius: 14px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #0b8f8a;
        }

        .bottom-text {
            text-align: center;
            margin-top: 24px;
            color: #374151;
            font-size: 15px;
        }

        .bottom-text a,
        .back-link {
            color: #0891b2;
            font-weight: 700;
            text-decoration: none;
        }

        .bottom-text a:hover,
        .back-link:hover {
            opacity: 0.85;
        }

        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 22px 0;
        }

        .back-link-wrap {
            text-align: center;
        }

        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 22px;
            }

            h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-box">🩺</div>
        <h1>Create Your Account</h1>
        <div class="subtitle">Register for your patient account</div>

        <?php if (!empty($message)) : ?>
            <div class="message <?php echo $is_success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="grid">
                <div>
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" required>
                </div>

                <div>
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($middle_name ?? ''); ?>">
                </div>

                <div class="full">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" required>
                </div>

                <div>
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>

                <div>
                    <label for="phone">Phone *</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                </div>

                <div>
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex">
                        <option value="">Select sex</option>
                        <option value="male" <?php echo (($sex ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (($sex ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="prefer_not_to_say" <?php echo (($sex ?? '') === 'prefer_not_to_say') ? 'selected' : ''; ?>>Prefer not to say</option>
                    </select>
                </div>

                <div>
                    <label for="birth_date">Birth Date</label>
                    <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($birth_date ?? ''); ?>">
                </div>

                <div class="full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                </div>

                <div>
                    <label for="emergency_contact_name">Emergency Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($emergency_contact_name ?? ''); ?>">
                </div>

                <div>
                    <label for="emergency_contact_phone">Emergency Contact Phone</label>
                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($emergency_contact_phone ?? ''); ?>">
                </div>

                <div>
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div>
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="full">
                    <button type="submit" class="btn">Register</button>
                </div>
            </div>
        </form>

        <div class="bottom-text">
            Already have an account? <a href="patient-login.php">Login here</a>
        </div>

        <hr>

        <div class="back-link-wrap">
            <a href="patient-login.php" class="back-link">← Back to Patient Login</a>
        </div>
    </div>
</body>
</html>