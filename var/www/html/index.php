<?php
// ZERO-TRUST DATABASE CONNECTION
$servername = "<INSERT RDS Endpoint>";
$username = "<INSERT Username>";
$dbname = "workouttracker";
$password = trim(shell_exec('aws ssm get-parameter --name "<your parameter name ie /my/parameter/secret >" --with-decryption --query "Parameter.Value" --output text --region us-east-2'));

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$status_message = "";

// HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['workout_date'];
    $exercise = $_POST['exercise_name'];
    $weight = $_POST['weight'];
    $sets = $_POST['sets'];
    $reps = $_POST['reps'];
    $failure = isset($_POST['failure']) ? 1 : 0;

    // Prepared statement to prevent SQL Injection (A+ Security Practice!)
    $stmt = $conn->prepare("INSERT INTO logs (workout_date, exercise_name, weight, sets, reps, muscle_failure_achieved) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiii", $date, $exercise, $weight, $sets, $reps, $failure);

    if ($stmt->execute()) {
        $status_message = "<div class='alert success'>Workout logged successfully to the database!</div>";
    } else {
        $status_message = "<div class='alert error'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkoutTracker</title>
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            background-color: #1e1e1e;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 500px;
            border-top: 4px solid #bb86fc;
        }
        h1 {
            text-align: center;
            color: #bb86fc;
            margin-top: 0;
        }
        .node-badge {
            background-color: #333;
            color: #aaa;
            text-align: center;
            padding: 5px;
            border-radius: 6px;
            font-size: 0.85em;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: 600;
            color: #e0e0e0;
        }
        input[type="text"], input[type="number"], input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #333;
            background-color: #2c2c2c;
            color: white;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            margin-top: 20px;
            background-color: #2c2c2c;
            padding: 12px;
            border-radius: 6px;
        }
        .checkbox-container input {
            margin-right: 10px;
            transform: scale(1.3);
        }
        .checkbox-container label {
            margin: 0;
            color: #ff5252;
            font-weight: bold;
        }
        button {
            width: 100%;
            padding: 14px;
            background-color: #bb86fc;
            color: #000;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 25px;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #9965f4;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
        }
        .success { background-color: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid #4caf50; }
        .error { background-color: rgba(244, 67, 54, 0.2); color: #f44336; border: 1px solid #f44336; }
    </style>
</head>
<body>

    <div class="container">
        <h1>WorkoutTracker</h1>
        <div class="node-badge">Serving from: VM1</div>

        <?= $status_message ?>

        <form method="POST" action="">
            <label for="workout_date">Date</label>
            <input type="date" id="workout_date" name="workout_date" required>

            <label for="exercise_name">Exercise Name</label>
            <input type="text" id="exercise_name" name="exercise_name" placeholder="e.g., Incline Dumbbell Press" required>

            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="weight">Weight (lbs)</label>
                    <input type="number" id="weight" name="weight" step="0.5" placeholder="90" required>
                </div>
                <div style="flex: 1;">
                    <label for="sets">Total Sets</label>
                    <input type="number" id="sets" name="sets" placeholder="3" required>
                </div>
                <div style="flex: 1;">
                    <label for="reps">Reps (Last Set)</label>
                    <input type="number" id="reps" name="reps" placeholder="6" required>
                </div>
            </div>

            <button type="submit">Log Workout</button>
        </form>
    </div>

</body>
</html>
