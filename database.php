<?php
session_start();
$conn = new mysqli("localhost", "root", "", "bookstore_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// تسجيل مستخدم جديد
if (isset($_POST["signup"])) {
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT); // تشفير كلمة المرور

    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    if ($stmt->execute()) {
        echo "تم التسجيل بنجاح";
    } else {
        echo "خطأ أثناء التسجيل";
    }
}

// تسجيل الدخول
if (isset($_POST["login"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed);
        $stmt->fetch();
        if (password_verify($password, $hashed)) {
            $_SESSION["user_id"] = $user_id;
            echo "تم تسجيل الدخول بنجاح";
        } else {
            echo "كلمة المرور غير صحيحة";
        }
    } else {
        echo "المستخدم غير موجود";
    }
}

// إضافة كتاب للسلة
if (isset($_POST["add_to_cart"]) && isset($_SESSION["user_id"])) {
    $book_id = $_POST["book_id"];
    $user_id = $_SESSION["user_id"];

    $stmt = $conn->prepare("SELECT id FROM cart WHERE user_id=? AND book_id=?");
    $stmt->bind_param("ii", $user_id, $book_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE user_id = $user_id AND book_id = $book_id");
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $user_id, $book_id);
        $stmt->execute();
    }

    echo "تمت إضافة الكتاب إلى السلة";
}

// تنفيذ الشراء
if (isset($_POST["checkout"]) && isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];
    $result = $conn->query("SELECT c.quantity, b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = $user_id");

    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $total += $row["quantity"] * $row["price"];
    }

    $stmt = $conn->prepare("INSERT INTO orders (user_id, total) VALUES (?, ?)");
    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();

    $conn->query("DELETE FROM cart WHERE user_id = $user_id");
    echo "تمت عملية الشراء بنجاح. المبلغ الإجمالي: $total";
}

// عرض الكتب
$result = $conn->query("SELECT * FROM books");
echo "<h2>الكتب المتوفرة:</h2>";
while ($book = $result->fetch_assoc()) {
    echo "<div>
        <strong>{$book['title']}</strong> - {$book['author']} - {$book['price']} ريال
        <form method='POST'>
            <input type='hidden' name='book_id' value='{$book['id']}'>
            <button name='add_to_cart'>أضف للسلة</button>
        </form>
    </div>";
}

if (isset($_SESSION["user_id"])) {
    echo "<form method='POST'><button name='checkout'>شراء الآن</button></form>";
}
?>
