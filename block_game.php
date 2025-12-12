<?php
session_start();

// --- Configuration ---
define('BOARD_SIZE', 4);
define('HUMAN', 'H');
define('MACHINE', 'M');
define('EMPTY_CELL', '.');

// --- Game Logic Functions ---

function init_game() {
    $board = array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, EMPTY_CELL));
    // Machine on top, Human on bottom
    for ($c = 0; $c < BOARD_SIZE; $c++) {
        $board[0][$c] = MACHINE;
        $board[3][$c] = HUMAN;
    }
    return [
        'board' => $board,
        'message' => "Rule: Surround enemies completely to capture them.",
        'game_over' => false,
        'selected' => null
    ];
}

// --- NEW CAPTURE LOGIC (Recursive / Flood Fill) ---

// Helper: Find valid neighbors
function get_neighbors($r, $c) {
    $neighbors = [];
    $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];
    foreach ($directions as $d) {
        $nr = $r + $d[0];
        $nc = $c + $d[1];
        if ($nr >= 0 && $nr < BOARD_SIZE && $nc >= 0 && $nc < BOARD_SIZE) {
            $neighbors[] = [$nr, $nc];
        }
    }
    return $neighbors;
}

// Scans the board and removes ANY group that has 0 liberties (0 empty neighbors)
function remove_captured_groups(&$board) {
    $visited = array_fill(0, BOARD_SIZE, array_fill(0, BOARD_SIZE, false));
    $removed_counts = ['H' => 0, 'M' => 0];

    for ($r = 0; $r < BOARD_SIZE; $r++) {
        for ($c = 0; $c < BOARD_SIZE; $c++) {
            $piece = $board[$r][$c];
            
            // If it's a piece and we haven't checked this group yet
            if ($piece !== EMPTY_CELL && !$visited[$r][$c]) {
                
                // --- Start Group Check (BFS/DFS) ---
                $group = [];       // Stores coordinates of this group
                $has_liberty = false; // Does this group have an empty spot?
                $queue = [[$r, $c]];
                $visited[$r][$c] = true;
                $group[] = [$r, $c];
                
                $head = 0;
                while($head < count($queue)){
                    $curr = $queue[$head++];
                    $cr = $curr[0]; 
                    $cc = $curr[1];

                    // Check neighbors of this specific stone
                    foreach (get_neighbors($cr, $cc) as $n) {
                        $nr = $n[0]; $nc = $n[1];
                        $neighbor_val = $board[$nr][$nc];

                        if ($neighbor_val === EMPTY_CELL) {
                            $has_liberty = true; // Found breathing room!
                        } elseif ($neighbor_val === $piece && !$visited[$nr][$nc]) {
                            // Found a friend! Add to group to check them too
                            $visited[$nr][$nc] = true;
                            $queue[] = [$nr, $nc];
                            $group[] = [$nr, $nc];
                        }
                    }
                }

                // --- End Group Check ---

                // If the ENTIRE group found NO empty neighbors, remove them all
                if (!$has_liberty) {
                    foreach ($group as $g) {
                        $board[$g[0]][$g[1]] = EMPTY_CELL;
                        $removed_counts[$piece]++;
                    }
                }
            }
        }
    }
    return $removed_counts;
}

// Standard movement logic
function get_valid_moves($board, $player, $specific_piece = null) {
    $moves = [];
    $pieces = [];
    if ($specific_piece) {
        $pieces[] = $specific_piece;
    } else {
        for ($r = 0; $r < BOARD_SIZE; $r++) {
            for ($c = 0; $c < BOARD_SIZE; $c++) {
                if ($board[$r][$c] === $player) $pieces[] = [$r, $c];
            }
        }
    }

    foreach ($pieces as $p) {
        foreach (get_neighbors($p[0], $p[1]) as $n) {
            if ($board[$n[0]][$n[1]] === EMPTY_CELL) {
                $moves[] = ['from' => $p, 'to' => $n];
            }
        }
    }
    return $moves;
}

function make_move(&$board, $move) {
    $piece = $board[$move['from'][0]][$move['from'][1]];
    $board[$move['from'][0]][$move['from'][1]] = EMPTY_CELL;
    $board[$move['to'][0]][$move['to'][1]] = $piece;
}

function count_pieces($board, $player) {
    $count = 0;
    foreach ($board as $row) {
        foreach ($row as $cell) {
            if ($cell === $player) $count++;
        }
    }
    return $count;
}

// AI: Looks for moves that capture you or save itself
function get_machine_move($board) {
    $moves = get_valid_moves($board, MACHINE);
    if (empty($moves)) return null;

    $best_move = null;
    $best_score = -99999;

    foreach ($moves as $move) {
        $temp_board = $board;
        make_move($temp_board, $move);
        
        // Simulate captures
        $removed = remove_captured_groups($temp_board);
        
        // Score Calculation
        $my_count = count_pieces($temp_board, MACHINE);
        $human_count = count_pieces($temp_board, HUMAN);
        $my_mobility = count(get_valid_moves($temp_board, MACHINE));
        
        // 1. Prioritize killing Human (+100 per kill)
        // 2. Prioritize Staying Alive (High weight)
        // 3. Keep mobility
        $score = ($my_count * 50) - ($human_count * 100) + $my_mobility;

        // Random jitter to make AI less predictable
        $score += rand(0, 5);

        if ($score > $best_score) {
            $best_score = $score;
            $best_move = $move;
        }
    }
    return $best_move;
}

// --- Request Handling ---

if (!isset($_SESSION['game']) || isset($_GET['reset'])) {
    $_SESSION['game'] = init_game();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$game = &$_SESSION['game'];

if (!$game['game_over'] && isset($_GET['action'])) {
    
    if ($_GET['action'] == 'select') {
        $r = (int)$_GET['r']; $c = (int)$_GET['c'];
        if ($game['board'][$r][$c] === HUMAN) {
            $game['selected'] = [$r, $c];
            $game['message'] = "Selected. Move to an empty spot.";
        }
    }
    
    if ($_GET['action'] == 'move' && $game['selected']) {
        $to_r = (int)$_GET['r']; $to_c = (int)$_GET['c'];
        $from_r = $game['selected'][0]; $from_c = $game['selected'][1];

        // Basic Move Validation (Distance 1, Empty)
        if (abs($from_r - $to_r) + abs($from_c - $to_c) === 1 && $game['board'][$to_r][$to_c] === EMPTY_CELL) {
            
            // 1. Human Move
            make_move($game['board'], ['from' => [$from_r, $from_c], 'to' => [$to_r, $to_c]]);
            $game['selected'] = null;
            
            // 2. Resolve Captures (Did Human kill anyone? Did Human suicide?)
            $removed = remove_captured_groups($game['board']);
            $msg = "";
            if ($removed['M'] > 0) $msg .= "You captured " . $removed['M'] . " AI piece(s)! ";
            if ($removed['H'] > 0) $msg .= "Oops, you moved into a trap! ";

            if (count_pieces($game['board'], MACHINE) == 0) {
                $game['game_over'] = true; $game['message'] = "VICTORY! All AI pieces captured.";
            } else {
                // 3. Machine Move
                $ai_move = get_machine_move($game['board']);
                if ($ai_move) {
                    make_move($game['board'], $ai_move);
                    
                    // 4. Resolve Captures again
                    $removed2 = remove_captured_groups($game['board']);
                    if ($removed2['H'] > 0) $msg .= "AI captured your piece! ";
                    
                    if (count_pieces($game['board'], HUMAN) == 0) {
                        $game['game_over'] = true; $game['message'] = "DEFEAT! No pieces left.";
                    } else {
                        $game['message'] = $msg ?: "Your Turn.";
                    }
                } else {
                    $game['game_over'] = true; $game['message'] = "VICTORY! AI is stuck.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Surround Tactics</title>
    <style>
        body { font-family: sans-serif; text-align: center; background: #222; color: #ddd; }
        h1 { margin: 10px; }
        .board { 
            display: grid; grid-template-columns: repeat(4, 80px); gap: 5px; 
            background: #444; width: 345px; margin: 20px auto; padding: 5px; border-radius: 5px;
        }
        .cell {
            width: 80px; height: 80px; background: #eee;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; position: relative;
        }
        .human { background-color: #2E7D32; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center; font-weight:bold; box-shadow: 2px 2px 5px rgba(0,0,0,0.5);}
        .machine { background-color: #c62828; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center; font-weight:bold; box-shadow: 2px 2px 5px rgba(0,0,0,0.5);}
        .selected { border: 4px solid #FFD700; }
        .valid-move { background-color: #ddd; }
        .dot { width: 15px; height: 15px; background: #2E7D32; border-radius: 50%; opacity: 0.6; }
        .btn { padding: 10px 20px; background: #555; color: #fff; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Surround Tactics</h1>
    <div style="height:30px; color: #FFD700; font-weight:bold;"><?php echo $game['message']; ?></div>

    <div class="board">
        <?php 
        $valid_dest = [];
        if ($game['selected']) {
            foreach (get_valid_moves($game['board'], HUMAN, $game['selected']) as $m) 
                $valid_dest[$m['to'][0].'_'.$m['to'][1]] = true;
        }

        for ($r = 0; $r < BOARD_SIZE; $r++): 
            for ($c = 0; $c < BOARD_SIZE; $c++): 
                $cell = $game['board'][$r][$c];
                $sel = ($game['selected'] && $game['selected'][0] == $r && $game['selected'][1] == $c);
                $is_dest = isset($valid_dest[$r.'_'.$c]);
                
                $link = null;
                if (!$game['game_over']) {
                    if ($cell === HUMAN) $link = "?action=select&r=$r&c=$c";
                    elseif ($is_dest) $link = "?action=move&r=$r&c=$c";
                }
                
                echo "<div class='cell " . ($is_dest ? "valid-move" : "") . "'>";
                if ($link) echo "<a href='$link' style='width:100%; height:100%; display:flex; align-items:center; justify-content:center; text-decoration:none;'>";
                
                if ($cell === HUMAN) echo "<div class='human " . ($sel ? 'selected' : '') . "'>You</div>";
                elseif ($cell === MACHINE) echo "<div class='machine'>AI</div>";
                elseif ($is_dest) echo "<div class='dot'></div>";
                
                if ($link) echo "</a>";
                echo "</div>";
            endfor; 
        endfor; 
        ?>
    </div>
    <a href="?reset=1" class="btn">Restart Game</a>
</body>
</html>