<?php

    try{
        $conn = new PDO("mysql:host=localhost;dbname=chat-app", "root", "");
    }
    catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }

?>