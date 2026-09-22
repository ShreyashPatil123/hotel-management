<?php require 'includes/functions.php'; unset($_SESSION['user']); flash('main','You have been logged out.'); redirect('login.php'); ?>
