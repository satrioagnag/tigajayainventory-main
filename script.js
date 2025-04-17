document.getElementById("loginForm").addEventListener("submit", async function (event) {
    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    const response = await fetch("login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
    });

    const result = await response.json();
    console.log(result); // 🔹 Cek response di browser console

    if (result.success) {
        sessionStorage.setItem("isAuthenticated", "true");
        sessionStorage.setItem("role", result.role);
        
        if (result.role === "Kasir") {
            window.location.href = "kasir/dashboard.php";
        } else if (result.role === "Admin") {
            window.location.href = "admin/dashboard.php";
        } else if (result.role === "Gudang") {
            window.location.href = "gudang/dashboard.php";
        } else {
            window.location.href = "index.html"; // Default jika role tidak dikenali
        }
    } else {
        document.getElementById("error-message").style.display = "block";
    }
});
