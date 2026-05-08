// --- NEW LOGIC: Password Visibility Toggle ---
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function () {
    // 1. Swap the type attribute
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // 2. Swap the emoji icon
    this.textContent = type === 'password' ? '👁️' : '🙈';
});

// --- ORIGINAL LOGIC: Login Flow ---
function login(){

    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    // Admin
    if(username === "admin" && password === "admin123"){
        window.location.href = "admin.html";
    }

    // Project Manager
    else if(username === "manager" && password === "manager123"){
        window.location.href = "project-manager.html";
    }

    // Site Engineer
    else if(username === "engineer" && password === "engineer123"){
        window.location.href = "site-engineer.html";
    }

    else{
        alert("Username atau password salah");
    }
}