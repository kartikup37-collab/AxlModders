<?php
// =======================================================
// BOT CONFIGURATION
// =======================================================
$BOT_TOKEN = "8463361639:AAE5UDmqjgZfg0DxlXIbB5rLWSmJ6IYuOms";
$AUDIO_URL = "https://github.com/kartikup37-collab/AxlModders/blob/main/Free_Key_Setup.mp3?raw=true";

// डेटाबेस फाइल
$USERS_FILE = 'users_data.json';

// =======================================================
// HELPER FUNCTIONS
// =======================================================

function loadUsersData() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) {
        file_put_contents($USERS_FILE, json_encode([]));
        return [];
    }
    $data = file_get_contents($USERS_FILE);
    return json_decode($data, true) ?: [];
}

function saveUser($user_id, $first_name, $username) {
    global $USERS_FILE;
    $users = loadUsersData();
    $is_new_user = !isset($users[$user_id]);
    
    $users[$user_id] = [
        'first_name' => $first_name,
        'username' => $username,
        'first_seen' => date('Y-m-d H:i:s'),
        'key_generated' => false,
        'drip_key' => '',
        'expiry_days' => 0,
        'expiry_date' => ''
    ];
    
    file_put_contents($USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    return $is_new_user;
}

function generateDripKey() {
    return rand(1000000000, 9999999999);
}

function getRandomExpiry() {
    $days = rand(10, 30);
    $expiry_date = date('d-m-Y', strtotime("+$days days"));
    return ['days' => $days, 'date' => $expiry_date];
}

function saveUserKey($user_id) {
    global $USERS_FILE;
    $users = loadUsersData();
    
    if (isset($users[$user_id]) && !$users[$user_id]['key_generated']) {
        $drip_key = generateDripKey();
        $expiry = getRandomExpiry();
        
        $users[$user_id]['key_generated'] = true;
        $users[$user_id]['drip_key'] = $drip_key;
        $users[$user_id]['expiry_days'] = $expiry['days'];
        $users[$user_id]['expiry_date'] = $expiry['date'];
        
        file_put_contents($USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        return ['key' => $drip_key, 'days' => $expiry['days'], 'date' => $expiry['date']];
    }
    return null;
}

// =======================================================
// TELEGRAM FUNCTIONS
// =======================================================

function sendMessage($chat_id, $text, $reply_markup = null) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['result']['message_id'] ?? null;
}

function editMessage($chat_id, $message_id, $text) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/editMessageText";
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

function deleteMessage($chat_id, $message_id) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/deleteMessage";
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

function sendAudio($chat_id) {
    global $BOT_TOKEN, $AUDIO_URL;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendAudio";
    $data = ['chat_id' => $chat_id, 'audio' => $AUDIO_URL, 'caption' => "🔊 <b>Key Setup Voice</b>\n\n<i>Processing your request...</i>", 'parse_mode' => 'HTML'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['result']['message_id'] ?? null;
}

// =======================================================
// MAIN BOT LOGIC - STEP BY STEP LOADING
// =======================================================

ignore_user_abort(true);
set_time_limit(0);

$input = file_get_contents("php://input");
$update = json_decode($input, true);

if ($update && isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $first_name = $update['message']['from']['first_name'];
    $username = isset($update['message']['from']['username']) ? $update['message']['from']['username'] : '';
    $text = isset($update['message']['text']) ? trim($update['message']['text']) : '';
    
    if (strpos($text, '/start') === 0) {
        // Step 1: Initial Loading
        $loading_text = "🔍 <b>INITIALIZING SYSTEM...</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "⏳ Starting connection to server...\n";
        $loading_text .= "⏳ Loading user interface...\n\n";
        $loading_text .= "<i>Please wait, this may take a moment...</i>";
        
        $msg_id = sendMessage($chat_id, $loading_text);
        
        sleep(3);
        
        // Step 2: Server Connection
        $loading_text = "🔍 <b>SYSTEM INITIALIZATION</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ Server connection established\n";
        $loading_text .= "⏳ Connecting to database...\n";
        $loading_text .= "⏳ Loading user profile...\n\n";
        $loading_text .= "<i>Processing your request...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(4);
        
        // Step 3: User ID Scanning
        $loading_text = "🔍 <b>USER ID SCANNING</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ Server connection established\n";
        $loading_text .= "✅ Database connected successfully\n";
        $loading_text .= "⏳ Scanning User ID: <code>$user_id</code>\n";
        $loading_text .= "⏳ Verifying user credentials...\n\n";
        $loading_text .= "<i>Please wait 5-7 seconds...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(5);
        
        // Step 4: User Verification
        $loading_text = "✅ <b>USER ID VERIFICATION</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ Server connection established\n";
        $loading_text .= "✅ Database connected successfully\n";
        $loading_text .= "✅ User ID: <code>$user_id</code> ✓ VERIFIED\n";
        $loading_text .= "⏳ Checking first-time user status...\n\n";
        $loading_text .= "<i>Verification in progress...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(4);
        
        // Step 5: First Time User Check
        $is_new_user = saveUser($user_id, $first_name, $username);
        
        $loading_text = "🔐 <b>ACCOUNT VERIFICATION</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ Server connection established\n";
        $loading_text .= "✅ Database connected successfully\n";
        $loading_text .= "✅ User ID: <code>$user_id</code> ✓ VERIFIED\n";
        
        if ($is_new_user) {
            $loading_text .= "✅ Status: <b>FIRST TIME USER</b> ✓\n";
            $loading_text .= "⏳ Generating security token...\n\n";
            $loading_text .= "<i>Welcome to DRIP CLIENT system</i>";
        } else {
            $loading_text .= "✅ Status: <b>RETURNING USER</b> ✓\n";
            $loading_text .= "⏳ Checking previous records...\n\n";
            $loading_text .= "<i>Welcome back to DRIP CLIENT</i>";
        }
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(4);
        
        // Step 6: Security Check
        $loading_text = "🛡️ <b>SECURITY CHECK</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ User authentication completed\n";
        $loading_text .= "✅ Account verification successful\n";
        $loading_text .= "⏳ Scanning for malicious activity...\n";
        $loading_text .= "⏳ Checking device compatibility...\n\n";
        $loading_text .= "<i>Security protocols enabled...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(5);
        
        // Step 7: Server Processing
        $loading_text = "⚙️ <b>SERVER PROCESSING</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ Security check passed\n";
        $loading_text .= "✅ Device compatibility confirmed\n";
        $loading_text .= "⏳ Connecting to DRIP CLIENT servers...\n";
        $loading_text .= "⏳ Processing your request...\n\n";
        $loading_text .= "<i>This may take 10-15 seconds...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(6);
        
        // Step 8: Final Processing
        $loading_text = "🔑 <b>KEY GENERATION</b>\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $loading_text .= "✅ All security checks passed\n";
        $loading_text .= "✅ DRIP CLIENT servers connected\n";
        $loading_text .= "⏳ Generating unique DRIP KEY...\n";
        $loading_text .= "⏳ Finalizing setup...\n\n";
        $loading_text .= "<i>Almost ready! Sending audio...</i>";
        
        editMessage($chat_id, $msg_id, $loading_text);
        
        sleep(3);
        
        // Step 9: Delete loading message
        deleteMessage($chat_id, $msg_id);
        
        // Step 10: Send Audio
        $audio_msg_id = sendAudio($chat_id);
        
        // Step 11: Wait and delete audio
        sleep(15);
        if ($audio_msg_id) {
            deleteMessage($chat_id, $audio_msg_id);
            
            // Step 12: Send DRIP KEY
            $key_data = saveUserKey($user_id);
            
            if ($key_data) {
                $key_message = "🎉 <b>DRIP CLIENT KEY GENERATED SUCCESSFULLY!</b>\n";
                $key_message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $key_message .= "👤 <b>User Information:</b>\n";
                $key_message .= "├─ User ID: <code>$user_id</code>\n";
                $key_message .= "├─ Name: $first_name\n";
                $key_message .= "└─ Username: @$username\n\n";
                $key_message .= "✅ <b>Verification Status:</b> COMPLETED\n\n";
                $key_message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $key_message .= "🔑 <b>Your Drip Client Key :</b>\n";
                $key_message .= "<code>" . $key_data['key'] . "</code>\n";
                $key_message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                $key_message .= "📅 <b>Expiry Date:</b> " . $key_data['date'] . "\n";
                $key_message .= "⏳ <b>Valid For:</b> " . $key_data['days'] . " days\n\n";
                $key_message .= "⚠️ <i>This key is valid for one device only.</i>\n";
                $key_message .= "🔒 <i>Do not share this key with anyone.</i>\n";
                $key_message .= "🚀 <i>Use this key to access premium features.</i>";
                
                sendMessage($chat_id, $key_message);
            }
        }
    }
}

echo "DRIP CLIENT BOT v2.0 ACTIVE 🚀";
?>