<?php 
require 'auth.php';
require 'mydb.php';  

$error = ''; 
if (isset($_SESSION['user_id'])) {
    // Redirect based on role if already logged in
    if ($_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: order.php");
    }
    exit; 
}  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {     
    $username = trim($_POST['username']);     
    $password = $_POST['password'];      
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");     
    $stmt->bind_param("s", $username);     
    $stmt->execute();     
    $res = $stmt->get_result();      
    
    if ($user = $res->fetch_assoc()) {         
        if (password_verify($password, $user['password'])) {             
            $_SESSION['user_id'] = $user['id'];             
            $_SESSION['username'] = $user['username'];             
            $_SESSION['role'] = $user['role'];             
            $_SESSION['LAST_ACTIVITY'] = time();
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: dashboard.php");
            } else {
                header("Location: order.php");
            }
            exit;         
        } else {             
            $error = "❌ Invalid password.";         
        }     
    } else {         
        $error = "❌ User not found.";     
    } 
} 
?> 
<!doctype html> 
<html lang="en"> 
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">   
    <link rel="icon" type="image/png" href="favicon.png">
    <title>Login - Rajaiswari</title>   
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecef 100%);
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08),
                        0 0 1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            background: transparent;
            border: none;
            padding: 45px 35px 25px;
            text-align: center;
        }

        .company-logo {
            width: 200px;
            height: auto;
            display: block;
            margin: 0 auto;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.04));
        }

        .card-body {
            padding: 25px 35px 35px;
        }

        .form-label {
            color: #1a1a1a;
            font-weight: 500;
            font-size: 0.875rem;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1.5px solid rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            color: #1a1a1a;
            padding: 13px 16px;
            font-size: 0.9375rem;
            font-weight: 400;
            transition: all 0.2s ease;
            width: 100%;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 48px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            color: #1a1a1a;
            background: rgba(0, 0, 0, 0.04);
        }

        .password-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }

        .form-control:focus {
            background: #ffffff;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.04);
            color: #1a1a1a;
            outline: none;
        }

        .form-control::placeholder {
            color: rgba(0, 0, 0, 0.35);
            font-weight: 400;
        }

        .btn-primary {
            background: #1a1a1a;
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-weight: 600;
            padding: 14px;
            font-size: 0.9375rem;
            letter-spacing: -0.01em;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-primary:hover {
            background: #000000;
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:active {
            transform: translateY(1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .alert {
            background: rgba(239, 68, 68, 0.08);
            border: 1.5px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0 20px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .divider span {
            padding: 0 12px;
            color: #6b7280;
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .footer-text {
            text-align: center;
            color: #6b7280;
            font-size: 0.8125rem;
            font-weight: 500;
            line-height: 1.5;
        }

        .footer-text b {
            font-weight: 600;
            color: #1a1a1a;
        }

        .mb-3 {
            margin-bottom: 1.25rem;
        }

        /* Enhanced focus states for better UX */
        .form-control:focus,
        .btn-primary:focus {
            outline: none;
        }

        /* Smooth transitions */
        .form-control,
        .btn-primary {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .login-container {
                max-width: 100%;
            }

            .glass-card {
                border-radius: 16px;
            }

            .card-header {
                padding: 35px 25px 20px;
            }

            .card-body {
                padding: 20px 25px 30px;
            }

            .company-logo {
                width: 160px;
            }

            .form-label {
                font-size: 0.8125rem;
            }

            .form-control {
                padding: 12px 14px;
                font-size: 0.875rem;
                border-radius: 8px;
            }

            .btn-primary {
                padding: 13px;
                font-size: 0.875rem;
                border-radius: 8px;
            }

            .footer-text {
                font-size: 0.75rem;
            }

            .divider {
                margin: 20px 0 16px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .login-container {
                max-width: 440px;
            }

            .company-logo {
                width: 180px;
            }
        }

        @media (min-width: 1025px) {
            .login-container {
                max-width: 460px;
            }

            .card-header {
                padding: 50px 40px 30px;
            }

            .card-body {
                padding: 30px 40px 40px;
            }

            .company-logo {
                width: 220px;
            }
        }

        /* Subtle entrance animation */
        .glass-card {
            animation: fadeInUp 0.4s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head> 
<body> 
    <div class="login-container">   
        <div class="glass-card">         
            <div class="card-header">           
                <img src="rajaiswari-wotbg.png" alt="Rajaiswari" class="company-logo">
            </div>         
            <div class="card-body">           
                <?php if ($error): ?>             
                    <div class="alert"><?= htmlspecialchars($error) ?></div>           
                <?php endif; ?>           
                <form method="POST">             
                    <div class="mb-3">               
                        <label class="form-label">Username</label>               
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" required autofocus>
                    </div>             
                    <div class="mb-3">               
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>             
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                    <div class="divider">
                        <span>QUICK NOTE</span>
                    </div>
                    <p class="footer-text">Default Credentials: <b>Use Valid Identification</b></p>           
                </form>         
            </div>       
        </div>     
    </div> 
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
        }
    </script>
</body> 
</html>