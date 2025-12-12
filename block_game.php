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
    
    // Machine on top (row 0), Human on bottom (row 3)
    for ($c = 0; $c < BOARD_SIZE; $c++) {
        $board[0][$c] = MACHINE;
        $board[3][$c] = HUMAN;
    }
    return [
        'board' => $board,
        'message' => "Your Turn! Click a Green piece to select it.",
        'game_over' => false,
        'winner' => null,
        'selected' => null
    ];
}

// Check specifically if ONE piece is trapped
function is_piece_trapped($board, $r, $c) {
    $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];
    foreach ($directions as $d) {
        $nr = $r + $d[0];
        $nc = $c + $d[1];
        // If there is at least one valid neighbor (inside board AND empty)
        if ($nr >= 0 && $nr < BOARD_SIZE && $nc >= 0 && $nc < BOARD_SIZE) {
            if ($board[$nr][$nc] === EMPTY_CELL) {
                return false; // It has a move, so it is NOT trapped
            }
        }
    }
    return true; // No empty neighbors, it is trapped
}

// NEW FUNCTION: Removes pieces that cannot move
function remove_trapped_pieces(&$board) {
    $removed_human = 0;
    $removed_machine = 0;

    // We must identify all trapped pieces FIRST, then remove them.
    // Otherwise, removing one might free up another during the loop.
    $to_remove = [];

    for ($r = 0; $r < BOARD_SIZE; $r++) {
        for ($c = 0; $c < BOARD_SIZE; $c++) {
            $piece = $board[$r][$c];
            if ($piece !== EMPTY_CELL) {
                if (is_piece_trapped($board, $r, $c)) {
                    $to_remove[] = [$r, $c, $piece];
                }
            }
        }
    }

    foreach ($to_remove as $item) {
        $r = $item[0]; $c = $item[1]; $p = $item[2];
        $board[$r][$c] = EMPTY_CELL; // Remove piece
        
        if ($p === HUMAN) $removed_human++;
        if ($p === MACHINE) $removed_machine++;
    }

    return ['H' => $removed_human, 'M' => $removed_machine];
}

function get_valid_moves($board, $player, $specific_piece = null) {
    $moves = [];
    $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];

    $pieces = [];
    if ($specific_piece) {
        $pieces[] = $specific_piece;
    } else {
        for ($r = 0; $r < BOARD_SIZE; $r++) {
            for ($c = 0; $c < BOARD_SIZE; $c++) {
                if ($board[$r][$c] === $player) {
                    $pieces[] = [$r, $c];
                }
            }
        }
    }

    foreach ($pieces as $p) {
        $r = $p[0]; $c = $p[1];
        foreach ($directions as $d) {
            $nr = $r + $d[0];
            $nc = $c + $d[1];
            if ($nr >= 0 && $nr < BOARD_SIZE && $nc >= 0 && $nc < BOARD_SIZE) {
                if ($board[$nr][$nc] === EMPTY_CELL) {
                    $moves[] = ['from' => [$r, $c], 'to' => [$nr, $nc]];
                }
            }
        }
    }
    return $moves;
}

function make_move(&$board, $move) {
    $fr = $move['from'][0]; $fc = $move['from'][1];
    $tr = $move['to'][0];   $tc = $move['to'][1];
    $piece = $board[$fr][$fc];
    $board[$fr][$fc] = EMPTY_CELL;
    $board[$tr][$tc] = $piece;
}

function count_pieces($board, $player) {
    $count = 0;
    for ($r = 0; $r < BOARD_SIZE; $r++) {
        for ($c = 0; $c < BOARD_SIZE; $c++) {
            if ($board[$r][$c] === $player) $count++;
        }
    }
    return $count;
}

// AI: Maximizes its freedom, minimizes yours, AVOIDS getting trapped
function get_machine_move($board) {
    $moves = get_valid_moves($board, MACHINE);
    if (empty($moves)) return null;

    $best_move = null;
    $best_score = -9999;

    foreach ($moves as $move) {
        $temp_board = $board;
        make_move($temp_board, $move);
        
        // Simulate immediate removal if this move traps anyone
        remove_trapped_pieces($temp_board);

        $my_pieces = count_pieces($temp_board, MACHINE);
        $human_pieces = count_pieces($temp_board, HUMAN);
        
        // Scoring logic:
        // 1. Keep my pieces alive (High priority)
        // 2. Kill human pieces (High priority)
        // 3. Keep mobility high
        
        $my_options = count(get_valid_moves($temp_board, MACHINE));
        
        $score = ($my_pieces * 10) - ($human_pieces * 10) + $my_options;

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
    
    // Select Piece
    if ($_GET['action'] == 'select') {
        $r = (int)$_GET['r'];
        $c = (int)$_GET['c'];
        if ($game['board'][$r][$c] === HUMAN) {
            $game['selected'] = [$r, $c];
            $game['message'] = "Piece selected.";
        }
    }
    
    // Move Piece
    if ($_GET['action'] == 'move' && $game['selected']) {
        $to_r = (int)$_GET['r'];
        $to_c = (int)$_GET['c'];
        $from_r = $game['selected'][0];
        $from_c = $game['selected'][1];

        if (abs($from_r - $to_r) + abs($from_c - $to_c) === 1 && $game['board'][$to_r][$to_c] === EMPTY_CELL) {
            
            // 1. HUMAN MOVES
            make_move($game['board'], ['from' => [$from_r, $from_c], 'to' => [$to_r, $to_c]]);
            $game['selected'] = null;
            
            // 2. CHECK & REMOVE TRAPPED PIECES
            $removed = remove_trapped_pieces($game['board']);
            $msg_add = "";
            if ($removed['M'] > 0) $msg_add .= " You captured " . $removed['M'] . " AI piece(s)!";
            if ($removed['H'] > 0) $msg_add .= " You lost " . $removed['H'] . " piece(s)!";

            // 3. CHECK VICTORY (Human)
            if (count_pieces($game['board'], MACHINE) == 0) {
                $game['game_over'] = true;
                $game['message'] = "VICTORY! All AI pieces captured.";
            } else {
                
                // 4. MACHINE MOVES
                $ai_move = get_machine_move($game['board']);
                
                if ($ai_move) {
                    make_move($game['board'], $ai_move);
                    
                    // 5. CHECK & REMOVE TRAPPED PIECES AGAIN
                    $removed_2 = remove_trapped_pieces($game['board']);
                    if ($removed_2['H'] > 0) $msg_add .= " AI captured your piece!";
                    
                    // 6. CHECK VICTORY (Machine)
                    if (count_pieces($game['board'], HUMAN) == 0) {
                        $game['game_over'] = true;
                        $game['message'] = "DEFEAT! All your pieces were captured.";
                    } else {
                        $game['message'] = "Machine moved." . $msg_add;
                    }
                } else {
                    // Machine has pieces but NO moves (Stalemate/Win condition depending on preference)
                    // In this version, if AI cannot move but is not dead yet, it passes turn or loses.
                    // Let's rule that if you can't move, you lose.
                    $game['game_over'] = true;
                    $game['message'] = "VICTORY! Machine is stuck.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Elimination Game</title>
    <style>
        body { font-family: sans-serif; text-align: center; background: #222; color: white;}
        h1 { margin-bottom: 5px; }
        .board { 
            display: grid; 
            grid-template-columns: repeat(4, 80px); 
            gap: 5px; 
            background: #444; 
            width: 345px; 
            margin: 20px auto; 
            padding: 5px;
            border-radius: 5px;
        }
        .cell {
            width: 80px; height: 80px;
            background: #eee;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; position: relative;
        }
        .human { background-color: #2E7D32; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center; font-weight:bold;}
        .machine { background-color: #c62828; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center; font-weight:bold;}
        .selected { border: 4px solid #FFD700; box-shadow: 0 0 10px #FFD700; }
        .valid-move { background-color: #ddd; }
        .dot { width: 20px; height: 20px; background: #2E7D32; border-radius: 50%; opacity: 0.5; }
        .status { margin: 15px; font-size: 1.2em; color: #bbb; height: 30px;}
        .btn { padding: 10px 20px; background: #555; color: #fff; text-decoration: none; border-radius: 5px; border: 1px solid #777;}
        .btn:hover { background: #777; }
    </style>
</head>
<body>

    <h1>Elimination Tactics</h1>
    <div class="status"><?php echo $game['message']; ?></div>

    <div class="board">
        <?php 
        $valid_destinations = [];
        if ($game['selected']) {
            $possible_moves = get_valid_moves($game['board'], HUMAN, $game['selected']);
            foreach ($possible_moves as $m) $valid_destinations[$m['to'][0] . '_' . $m['to'][1]] = true;
        }

        for ($r = 0; $r < BOARD_SIZE; $r++): 
            for ($c = 0; $c < BOARD_SIZE; $c++): 
                $cell = $game['board'][$r][$c];
                $is_selected = ($game['selected'] && $game['selected'][0] == $r && $game['selected'][1] == $c);
                $is_valid_dest = isset($valid_destinations[$r.'_'.$c]);
                
                $link = null;
                if (!$game['game_over']) {
                    if ($cell === HUMAN) $link = "?action=select&r=$r&c=$c";
                    elseif ($is_valid_dest) $link = "?action=move&r=$r&c=$c";
                }
                
                echo "<div class='cell " . ($is_valid_dest ? "valid-move" : "") . "'>";
                if ($link) echo "<a href='$link' style='width:100%; height:100%; display:flex; align-items:center; justify-content:center; text-decoration:none;'>";
                
                if ($cell === HUMAN) echo "<div class='human " . ($is_selected ? 'selected' : '') . "'>You</div>";
                elseif ($cell === MACHINE) echo "<div class='machine'>AI</div>";
                elseif ($is_valid_dest) echo "<div class='dot'></div>";
                
                if ($link) echo "</a>";
                echo "</div>";
            endfor; 
        endfor; 
        ?>
    </div>

    <a href="?reset=1" class="btn">Restart Game</a>

</body>
</html>