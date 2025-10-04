<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f0f2f5;
            /* Lighter background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .result-container {
            max-width: 550px;
            /* Slightly smaller */
            width: 100%;
            padding: 50px;
            /* More padding */
            background-color: white;
            border-radius: 12px;
            /* Softer corners */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            /* More refined shadow */
            text-align: center;
            animation: fadeIn 0.8s ease-out;
            /* Add a subtle fade-in animation */
        }

        h1 {
            color: #343a40;
            /* Darker heading */
            font-weight: 600;
            margin-bottom: 30px;
        }

        .score-display {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 30px 0;
            padding: 15px 30px;
            background-color: #e6f0ff;
            /* Light blue background for score */
            border-radius: 8px;
            border: 1px solid #cce0ff;
        }

        .score {
            font-size: 3.5rem;
            /* Slightly smaller for elegance */
            font-weight: 700;
            color: #0056b3;
            /* Deeper blue */
            line-height: 1;
        }

        .total-score {
            font-size: 1.8rem;
            color: #6c757d;
            font-weight: 500;
            margin-left: 10px;
            line-height: 1;
        }

        .message {
            font-size: 1.1rem;
            color: #495057;
            margin-top: 25px;
            margin-bottom: 30px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 10px 25px;
            font-size: 1rem;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="result-container">
        <h1><i class="bi bi-check-circle-fill text-success me-2"></i> Completed</h1>

        <div class="score-display">
            <span class="score"><?= $score ?></span>
            <span class="total-score">/ <?= $total_items ?></span>
        </div>

        <a href="../student/student.php" class="btn btn-primary mt-3"><i class="bi bi-house-door-fill me-2"></i>Back to Dashboard</a>
    </div>
</body>

</html>