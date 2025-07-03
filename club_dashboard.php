<?php
session_start();
require 'db.php'; // Your DB connection

if (!isset($_GET['club_id']) || !is_numeric($_GET['club_id'])) {
    echo "No club selected.";
    exit;
}

$club_id = intval($_GET['club_id']);
