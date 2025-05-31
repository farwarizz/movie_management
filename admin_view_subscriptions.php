php

  admin_view_subscriptions.php
  This file allows administrators to view, delete, and UPDATE user streaming subscriptions.
 

session_start();
require_once 'db_connect.php';

 Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])  $_SESSION['user_type'] !== 'admin') {
    header(Location login.php);
    exit();
}

$message = '';
$edit_subscription_data = null;  To hold data if a subscription is being edited

 Function to sanitize input
function sanitize_input($conn, $data) {
    return $conn-real_escape_string(htmlspecialchars(strip_tags($data)));
}

 --- Handle Update Subscription ---
if ($_SERVER[REQUEST_METHOD] == POST && isset($_POST['action']) && $_POST['action'] === 'update_subscription') {
    $subscription_id = (int)$_POST['subscription_id'];
    $user_id = (int)$_POST['user_id'];
    $platform_id = (int)$_POST['platform_id'];
    $start_date = sanitize_input($conn, $_POST['start_date']);
    $end_date = sanitize_input($conn, $_POST['end_date']);
    $plan_type = sanitize_input($conn, $_POST['plan_type']);

    if (empty($user_id)  empty($platform_id)  empty($start_date)  empty($end_date)  empty($plan_type)) {
        $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'All fields are required for update.div;
    } else {
        $stmt = $conn-prepare(UPDATE subscription SET user_id = , platform_id = , start_date = , end_date = , plan_type =  WHERE subscription_id = );
        if ($stmt) {
            $stmt-bind_param(iisssi, $user_id, $platform_id, $start_date, $end_date, $plan_type, $subscription_id);
            if ($stmt-execute()) {
                $message = div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'Subscription updated successfully!div;
            } else {
                $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'Error updating subscription  . $stmt-error . div;
            }
            $stmt-close();
        } else {
            $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'Database error  . $conn-error . div;
        }
    }
}

 --- Handle Delete Subscription ---
if (isset($_GET['delete_id'])) {
    $subscription_id = (int)$_GET['delete_id'];
    $stmt = $conn-prepare(DELETE FROM subscription WHERE subscription_id = );
    if ($stmt) {
        $stmt-bind_param(i, $subscription_id);
        if ($stmt-execute()) {
            $message = div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'Subscription deleted successfully!div;
        } else {
            $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'Error deleting subscription  . $stmt-error . div;
        }
        $stmt-close();
    } else {
        $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'Database error  . $conn-error . div;
    }
    header(Location admin_view_subscriptions.phpmessage= . urlencode(strip_tags($message)));
    exit();
}

 Handle message from redirect
if (isset($_GET['message'])) {
    $message = div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert' . htmlspecialchars($_GET['message']) . div;
}

 --- Handle Edit Subscription Request (Populate form for update) ---
if (isset($_GET['edit_id'])) {
    $subscription_id = (int)$_GET['edit_id'];
    $stmt = $conn-prepare(SELECT subscription_id, user_id, platform_id, start_date, end_date, plan_type FROM subscription WHERE subscription_id = );
    if ($stmt) {
        $stmt-bind_param(i, $subscription_id);
        $stmt-execute();
        $result = $stmt-get_result();
        if ($result-num_rows === 1) {
            $edit_subscription_data = $result-fetch_assoc();
        } else {
            $message = div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'Subscription not found for editing.div;
        }
        $stmt-close();
    }
}

 --- Fetch All Subscriptions for Display ---
$subscriptions = [];
$sql = SELECT s.subscription_id, u.name AS user_name, u.user_id, sp.platform_name, sp.platform_id, s.start_date, s.end_date, s.plan_type
        FROM subscription s
        JOIN user u ON s.user_id = u.user_id
        JOIN streaming_platform sp ON s.platform_id = sp.platform_id
        ORDER BY s.end_date DESC, s.subscription_id DESC;
$result = $conn-query($sql);
if ($result) {
    while ($row = $result-fetch_assoc()) {
        $subscriptions[] = $row;
    }
} else {
    $message = div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'Error fetching subscriptions  . $conn-error . div;
}

 --- Fetch data for dropdowns (Users, Platforms) ---
$users_for_dropdown = [];
$result_users = $conn-query(SELECT user_id, name FROM user ORDER BY name ASC);
if ($result_users) {
    while ($row = $result_users-fetch_assoc()) {
        $users_for_dropdown[] = $row;
    }
}

$platforms_for_dropdown = [];
$result_platforms = $conn-query(SELECT platform_id, platform_name FROM streaming_platform ORDER BY platform_name ASC);
if ($result_platforms) {
    while ($row = $result_platforms-fetch_assoc()) {
        $platforms_for_dropdown[] = $row;
    }
}

$conn-close();


!DOCTYPE html
html lang=en
head
    meta charset=UTF-8
    meta name=viewport content=width=device-width, initial-scale=1.0
    titleView Subscriptions - Admin Paneltitle
    script src=httpscdn.tailwindcss.comscript
    link href=httpsfonts.googleapis.comcss2family=Interwght@400;500;600;700&display=swap rel=stylesheet
    style
        body {
            font-family 'Inter', sans-serif;
            background-color #f0f2f5;
        }
        table {
            width 100%;
            border-collapse collapse;
        }
        th, td {
            padding 12px 15px;
            text-align left;
            border-bottom 1px solid #e2e8f0;
        }
        th {
            background-color #f8fafc;
            font-weight 600;
            color #4a5568;
            text-transform uppercase;
            font-size 0.75rem;
        }
        trhover {
            background-color #f0f2f5;
        }
        .action-buttons {
            display flex;
            gap 8px;
        }
        .action-buttons a, .action-buttons button {
            padding 6px 12px;
            border-radius 5px;
            font-size 0.875rem;
            text-decoration none;
            color white;
            cursor pointer;
            transition background-color 0.2s ease-in-out;
        }
        .action-buttons .edit-btn { background-color #3b82f6; }  blue-500 
        .action-buttons .edit-btnhover { background-color #2563eb; }  blue-600 
        .action-buttons .delete-btn { background-color #ef4444; }  red-500 
        .action-buttons .delete-btnhover { background-color #dc2626; }  red-600 

         Modal specific styles 
        .modal {
            display none;  Hidden by default 
            position fixed;  Stay in place 
            z-index 1000;  Sit on top 
            left 0;
            top 0;
            width 100%;  Full width 
            height 100%;  Full height 
            overflow auto;  Enable scroll if needed 
            background-color rgba(0,0,0,0.4);  Black w opacity 
            justify-content center;
            align-items center;
        }
        .modal-content {
            background-color #fefefe;
            margin auto;
            padding 20px;
            border-radius 8px;
            width 90%;
            max-width 600px;
            box-shadow 0 4px 6px rgba(0, 0, 0, 0.1);
            position relative;
        }
        .close-button {
            color #aaa;
            float right;
            font-size 28px;
            font-weight bold;
            position absolute;
            top 10px;
            right 20px;
        }
        .close-buttonhover,
        .close-buttonfocus {
            color black;
            text-decoration none;
            cursor pointer;
        }
    style
head
body class=min-h-screen bg-gray-100 flex flex-col
    nav class=bg-indigo-700 p-4 shadow-md
        div class=container mx-auto flex justify-between items-center
            h1 class=text-white text-2xl font-boldView Subscriptionsh1
            div class=flex items-center space-x-4
                span class=text-white text-lgWelcome, Admin php echo htmlspecialchars($_SESSION['user_name']); !span
                a href=admin.php class=bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hoverbg-indigo-100Admin Dashboarda
                a href=logout.php class=bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hoverbg-indigo-100Logouta
            div
        div
    nav

    main class=flex-grow container mx-auto p-6
        php echo $message;  Display messages 

        h2 class=text-2xl font-bold text-gray-800 mb-6All User Subscriptionsh2
        php if (!empty($subscriptions)) 
            div class=bg-white rounded-lg shadow-md overflow-hidden
                div class=overflow-x-auto
                    table
                        thead
                            tr
                                thSubscription IDth
                                thUserth
                                thPlatformth
                                thPlan Typeth
                                thStart Dateth
                                thEnd Dateth
                                thActionsth
                            tr
                        thead
                        tbody
                            php foreach ($subscriptions as $subscription) 
                                tr
                                    tdphp echo htmlspecialchars($subscription['subscription_id']); td
                                    tdphp echo htmlspecialchars($subscription['user_name']); td
                                    tdphp echo htmlspecialchars($subscription['platform_name']); td
                                    tdphp echo htmlspecialchars($subscription['plan_type']); td
                                    tdphp echo htmlspecialchars($subscription['start_date']); td
                                    tdphp echo htmlspecialchars($subscription['end_date']); td
                                    td class=action-buttons
                                        button onclick=openEditSubscriptionModal(php echo htmlspecialchars(json_encode($subscription)); ) class=edit-btnEditbutton
                                        button onclick=confirmDelete(php echo htmlspecialchars($subscription['subscription_id']); ) class=delete-btnDeletebutton
                                    td
                                tr
                            php endforeach; 
                        tbody
                    table
                div
            div
        php else 
            p class=text-gray-700 text-lg text-center py-10No subscriptions found in the database.p
        php endif; 
    main

    footer class=bg-gray-800 text-white text-center p-4 mt-auto
        div class=container mx-auto
            p&copy; php echo date('Y');  Movies Management System. Admin Panel.p
        div
    footer

    div id=editSubscriptionModal class=modal
        div class=modal-content
            span class=close-button onclick=closeEditSubscriptionModal()&times;span
            h3 class=text-2xl font-bold text-gray-800 mb-4Edit Subscriptionh3
            form action=admin_view_subscriptions.php method=POST class=space-y-4
                input type=hidden name=action value=update_subscription
                input type=hidden name=subscription_id id=edit_subscription_id

                div
                    label for=edit_user_id class=block text-sm font-medium text-gray-700Userlabel
                    select id=edit_user_id name=user_id required
                            class=mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focusoutline-none focusring-indigo-500 focusborder-indigo-500 smtext-sm
                        php foreach ($users_for_dropdown as $user) 
                            option value=php echo htmlspecialchars($user['user_id']); php echo htmlspecialchars($user['name']); option
                        php endforeach; 
                    select
                div

                div
                    label for=edit_platform_id class=block text-sm font-medium text-gray-700Platformlabel
                    select id=edit_platform_id name=platform_id required
                            class=mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focusoutline-none focusring-indigo-500 focusborder-indigo-500 smtext-sm
                        php foreach ($platforms_for_dropdown as $platform) 
                            option value=php echo htmlspecialchars($platform['platform_id']); php echo htmlspecialchars($platform['platform_name']); option
                        php endforeach; 
                    select
                div

                div
                    label for=edit_start_date class=block text-sm font-medium text-gray-700Start Datelabel
                    input type=date id=edit_start_date name=start_date required
                           class=mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focusoutline-none focusring-indigo-500 focusborder-indigo-500 smtext-sm
                div

                div
                    label for=edit_end_date class=block text-sm font-medium text-gray-700End Datelabel
                    input type=date id=edit_end_date name=end_date required
                           class=mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focusoutline-none focusring-indigo-500 focusborder-indigo-500 smtext-sm
                div

                div
                    label for=edit_plan_type class=block text-sm font-medium text-gray-700Plan Typelabel
                    input type=text id=edit_plan_type name=plan_type required
                           class=mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focusoutline-none focusring-indigo-500 focusborder-indigo-500 smtext-sm
                div

                button type=submit
                        class=w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hoverbg-indigo-700 focusoutline-none focusring-2 focusring-offset-2 focusring-indigo-500
                    Update Subscription
                button
            form
        div
    div

    script
        const editSubscriptionModal = document.getElementById('editSubscriptionModal');
        const editSubscriptionIdInput = document.getElementById('edit_subscription_id');
        const editUserIdSelect = document.getElementById('edit_user_id');
        const editPlatformIdSelect = document.getElementById('edit_platform_id');
        const editStartDateInput = document.getElementById('edit_start_date');
        const editEndDateInput = document.getElementById('edit_end_date');
        const editPlanTypeInput = document.getElementById('edit_plan_type');

        function openEditSubscriptionModal(subscription) {
            editSubscriptionIdInput.value = subscription.subscription_id;
            editUserIdSelect.value = subscription.user_id;
            editPlatformIdSelect.value = subscription.platform_id;
            editStartDateInput.value = subscription.start_date;
            editEndDateInput.value = subscription.end_date;
            editPlanTypeInput.value = subscription.plan_type;
            editSubscriptionModal.style.display = 'flex';
        }

        function closeEditSubscriptionModal() {
            editSubscriptionModal.style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm(`Are you sure you want to delete subscription ID ${id} This action cannot be undone.`)) {
                window.location.href = `admin_view_subscriptions.phpdelete_id=${id}`;
            }
        }

         Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == editSubscriptionModal) {
                closeEditSubscriptionModal();
            }
        }
    script
body
html
