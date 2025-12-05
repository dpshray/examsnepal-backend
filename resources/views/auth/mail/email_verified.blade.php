<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Email Verified</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    href="https://unpkg.com/lucide-static@latest/font/lucide.css"
    rel="stylesheet"
  />
</head>
<body class="h-screen bg-gradient-to-br from-orange-50 to-blue-50 flex items-center justify-center p-4">
  <div class="w-full max-w-md mx-auto shadow-xl rounded-2xl bg-white">
    <div class="p-8 text-center space-y-6">
      <div class="flex justify-center">
        <div class="relative">
          <i class="lucide lucide-check-circle w-16 h-16 text-green-500 animate-bounce"></i>
          <div class="absolute inset-0 w-16 h-16 bg-green-500/20 rounded-full animate-ping"></div>
        </div>
      </div>
      <div class="space-y-2">
        <h1 class="text-3xl font-bold text-gray-900">Congratulations!</h1>
        <p class="text-gray-600">
          Your email has been successfully verified.
        </p>
      </div>
    </div>
  </div>
</body>
</html>