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
$search_results = [];
$search_query = "";

// 1. HANDLE FORM SUBMISSION (LOGGING A WORKOUT)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_workout'])) {
    $date = trim($_POST['workout_date']);
    $exercise = trim(htmlspecialchars($_POST['exercise_name']));
    $weight = filter_var($_POST['weight'], FILTER_VALIDATE_FLOAT);
    $sets = filter_var($_POST['sets'], FILTER_VALIDATE_INT);
    $reps = filter_var($_POST['reps'], FILTER_VALIDATE_INT);

    if (empty($date) || empty($exercise) || $weight === false || $sets === false || $reps === false) {
        $status_message = "<div class='alert error'>Error: Please fill out all fields with valid data.</div>";
    } elseif ($weight < 0 || $sets < 1 || $reps < 0) {
        $status_message = "<div class='alert error'>Error: Numbers cannot be negative.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO logs (workout_date, exercise_name, weight, sets, reps) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $date, $exercise, $weight, $sets, $reps);

        if ($stmt->execute()) {
            $status_message = "<div class='alert success'>Workout logged successfully to the database!</div>";
        } else {
            $status_message = "<div class='alert error'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// 2. HANDLE SEARCH REQUEST
if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($_GET['search'])) {
    $search_query = trim(htmlspecialchars($_GET['search']));
    // The % symbols act as wildcards, so it finds the word anywhere in the string
    $like_query = "%" . $search_query . "%";

    // Select results, ordered by the most recent date first
    $stmt = $conn->prepare("SELECT workout_date, exercise_name, weight, sets, reps FROM logs WHERE exercise_name LIKE ? ORDER BY workout_date DESC LIMIT 10");
    $stmt->bind_param("s", $like_query);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
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
            max-width: 600px; /* Slightly wider to fit the table nicely */
            border-top: 4px solid #bb86fc;
            margin-bottom: 20px;
        }
        h1 { text-align: center; color: #bb86fc; margin-top: 0; }
        h2 { border-bottom: 1px solid #333; padding-bottom: 10px; margin-top: 30px; font-size: 1.2em;}
        .node-badge {
            background-color: #333; color: #aaa; text-align: center;
            padding: 5px; border-radius: 6px; font-size: 0.85em; margin-bottom: 20px;
        }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #e0e0e0; }
        input[type="text"], input[type="number"], input[type="date"] {
            width: 100%; padding: 12px; border: 1px solid #333;
            background-color: #2c2c2c; color: white; border-radius: 6px; box-sizing: border-box;
        }
        button {
            width: 100%; padding: 14px; background-color: #bb86fc; color: #000;
            border: none; border-radius: 6px; font-size: 1.1em; font-weight: bold;
            cursor: pointer; margin-top: 25px; transition: background-color 0.2s;
        }
        button:hover { background-color: #9965f4; }
        .btn-secondary { background-color: #03dac6; margin-top: 10px; }
        .btn-secondary:hover { background-color: #00b3a6; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; text-align: center; font-weight: bold; }
        .success { background-color: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid #4caf50; }
        .error { background-color: rgba(244, 67, 54, 0.2); color: #f44336; border: 1px solid #f44336; }

        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background-color: #2c2c2c; color: #bb86fc; }
        tr:hover { background-color: #252525; }
        .no-results { text-align: center; padding: 20px; color: #aaa; font-style: italic; }
    </style>
</head>
<body>

    <div class="container">
        <h1>WorkoutTracker</h1>
        <div class="node-badge">Serving from: VMplaceholder</div>

        <?= $status_message ?>

        <form method="POST" action="">
            <input type="hidden" name="log_workout" value="1">

            <label for="workout_date">Date</label>
            <input type="date" id="workout_date" name="workout_date" max="<?= date('Y-m-d') ?>" required>

            <label for="exercise_name">Exercise Name</label>
            <input type="text" id="exercise_name" name="exercise_name" placeholder="e.g., Incline Dumbbell Press" maxlength="255" required>

            <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                    <label for="weight">Weight (lbs)</label>
                    <input type="number" id="weight" name="weight" step="0.5" min="0" placeholder="90" required>
                </div>
                <div style="flex: 1;">
                    <label for="sets">Total Sets</label>
                    <input type="number" id="sets" name="sets" placeholder="3" min="1" required>
                </div>
                <div style="flex: 1;">
                    <label for="reps">Reps (Last Set)</label>
                    <input type="number" id="reps" name="reps" placeholder="6" min="0" required>
                </div>
            </div>

            <button type="submit">Log Workout</button>
        </form>

        <hr style="border-color: #333; margin: 40px 0;">

        <h2>Exercise History</h2>
        <form method="GET" action="">
            <label for="search">Search Past Workouts</label>
            <input type="text" id="search" name="search" placeholder="Search by exercise name..." value="<?= htmlspecialchars($search_query) ?>" required>
            <button type="submit" class="btn-secondary">Search History</button>
        </form>

        <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && !empty($_GET['search'])): ?>
            <?php if (count($search_results) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Exercise</th>
                            <th>Weight</th>
                            <th>Sets</th>
                            <th>Reps</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['workout_date']) ?></td>
                                <td><?= htmlspecialchars($row['exercise_name']) ?></td>
                                <td><?= htmlspecialchars($row['weight']) ?> lbs</td>
                                <td><?= htmlspecialchars($row['sets']) ?></td>
                                <td><?= htmlspecialchars($row['reps']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">No historical data found for "<?= htmlspecialchars($search_query) ?>".</div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

</body>
</html>
