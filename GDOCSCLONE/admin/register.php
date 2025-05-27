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
  <title>Create an Account</title>
</head>
<body class="bg-blue-800">
  <div class="flex items-center justify-center min-h-screen px-4">
    <div class="w-full max-w-lg bg-white rounded-lg shadow-xl p-8">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Create an account</h2>

      <form class="space-y-5" method="POST" action="core/handleforms.php">
        <?php  
          if (isset($_SESSION['message']) && isset($_SESSION['status'])) {
            $color = $_SESSION['status'] === "200" ? "green" : "red";
            echo "<h1 style='color: $color;'>{$_SESSION['message']}</h1>";
            unset($_SESSION['message']);
            unset($_SESSION['status']);
          }
        ?>

        <div>
          <label for="username" class="block mb-1 text-sm font-medium text-gray-900">Your username</label>
          <input id="username" name="username" type="text" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="email_address" class="block mb-1 text-sm font-medium text-gray-900">Email address</label>
          <input id="email_address" name="email_address" type="email" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="first_name" class="block mb-1 text-sm font-medium text-gray-900">First Name</label>
          <input id="first_name" name="first_name" type="text" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="last_name" class="block mb-1 text-sm font-medium text-gray-900">Last Name</label>
          <input id="last_name" name="last_name" type="text" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="password" class="block mb-1 text-sm font-medium text-gray-900">Password</label>
          <input id="password" name="password" type="password" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <div>
          <label for="confirmPassword" class="block mb-1 text-sm font-medium text-gray-900">Confirm password</label>
          <input id="confirmPassword" name="confirmPassword" type="password" 
            class="w-full px-4 py-2 text-sm border border-gray-300 rounded-lg bg-gray-50 text-gray-900" />
        </div>

        <p class="text-sm font-medium">Already have an account? <a href="login.php" class="text-blue-500">Login here</a></p>

        <button type="submit" name="insertNewUserBtn"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2.5 rounded-lg">
          Create an account
        </button>
      </form>
    </div>
  </div>
</body>
</html>