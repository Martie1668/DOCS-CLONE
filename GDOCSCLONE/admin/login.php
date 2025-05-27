<?php
require_once __DIR__ . "/core/dbconfig.php";
require_once __DIR__ . "/core/models.php";

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Login</title>
</head>
<body class="bg-blue-200">
  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-lg bg-white rounded-lg shadow-xl p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Login</h2>
      <form class="space-y-5" method="POST" action="core/handleforms.php">
            <h2>
            <?php  
                if (isset($_SESSION['message']) && isset($_SESSION['status'])) {

                  if ($_SESSION['status'] == "200") {
                    echo "<h1 style='color: green;'>{$_SESSION['message']}</h1>";
                  }

                  else {
                    echo "<h1 style='color: red;'>{$_SESSION['message']}</h1>"; 
                  }

                }
                unset($_SESSION['message']);
                unset($_SESSION['status']);
              ?>
            </h2>
        <div>
          <label for="username" class="block mb-1 text-sm font-medium text-gray-900">Username</label>
          <input id="username" name="username" type="text" placeholder="Enter your username" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="password" class="block mb-1 text-sm font-medium text-gray-900">Password</label>
          <input id="password" name="password" type="password" placeholder="••••••••" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <p class="text-large font-medium">Don't have an account? <a href="register.php" class="text-blue-400 font-medium">Register here</a></p>

        <button type="submit" name="loginUserBtn"
           class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300">
          LOG IN
        </button>
      </form>
    </div>
  </div>
</body>
</html>