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