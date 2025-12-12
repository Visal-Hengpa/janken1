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
        'selected' => null // Stores coordinates of piece user clicked: [row, col]
    ];
}

// Get all valid moves for a specific player (or specific piece)
function get_valid_moves($board, $player, $specific_piece = null) {
    $moves = [];
    $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]]; // Up, Down, Left, Right

    // Find pieces
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

// AI: Maximizes its freedom, minimizes yours
function get_machine_move($board) {
    $moves = get_valid_moves($board, MACHINE);
    if (empty($moves)) return null;

    $best_move = null;
    $best_score = -9999;

    foreach ($moves as $move) {
        $temp_board = $board;
        make_move($temp_board, $move);
        
        // Score: My Moves - Your Moves
        $my_options = count(get_valid_moves($temp_board, MACHINE));
        $human_options = count(get_valid_moves($temp_board, HUMAN));
        
        $score = $my_options - ($human_options * 1.5); // Weight blocking human higher

        if ($score > $best_score) {
            $best_score = $score;
            $best_move = $move;
        }
    }
    return $best_move;
}

// --- Request Handling ---

// Reset Game
if (!isset($_SESSION['game']) || isset($_GET['reset'])) {
    $_SESSION['game'] = init_game();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$game = &$_SESSION['game'];

// Handle User Input
if (!$game['game_over'] && isset($_GET['action'])) {
    
    // 1. User Selects a Piece
    if ($_GET['action'] == 'select') {
        $r = (int)$_GET['r'];
        $c = (int)$_GET['c'];
        if ($game['board'][$r][$c] === HUMAN) {
            $game['selected'] = [$r, $c];
            $game['message'] = "Piece selected. Click an empty spot to move.";
        }
    }
    
    // 2. User Moves to a spot
    if ($_GET['action'] == 'move' && $game['selected']) {
        $to_r = (int)$_GET['r'];
        $to_c = (int)$_GET['c'];
        $from_r = $game['selected'][0];
        $from_c = $game['selected'][1];

        // Validate Move (must be distance of 1)
        if (abs($from_r - $to_r) + abs($from_c - $to_c) === 1 && $game['board'][$to_r][$to_c] === EMPTY_CELL) {
            
            // Execute Human Move
            make_move($game['board'], ['from' => [$from_r, $from_c], 'to' => [$to_r, $to_c]]);
            $game['selected'] = null;
            
            // Check if Human Won (Machine blocked?)
            $machine_moves = get_valid_moves($game['board'], MACHINE);
            if (empty($machine_moves)) {
                $game['game_over'] = true;
                $game['message'] = "VICTORY! The Machine cannot move.";
            } else {
                // Machine Turn
                $ai_move = get_machine_move($game['board']);
                make_move($game['board'], $ai_move);
                
                // Check if Machine Won (Human blocked?)
                $human_moves = get_valid_moves($game['board'], HUMAN);
                if (empty($human_moves)) {
                    $game['game_over'] = true;
                    $game['message'] = "DEFEAT! You are blocked. The Machine wins.";
                } else {
                    $game['message'] = "Machine moved. Your turn!";
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Blocking Game (PHP)</title>
    <style>
        body { font-family: sans-serif; text-align: center; background: #f0f0f0; }
        h1 { color: #333; }
        .board { 
            display: grid; 
            grid-template-columns: repeat(4, 80px); 
            gap: 5px; 
            background: #333; 
            width: 345px; 
            margin: 20px auto; 
            padding: 5px;
            border-radius: 5px;
        }
        .cell {
            width: 80px; height: 80px;
            background: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: bold;
            text-decoration: none; color: #333;
        }
        .human { background-color: #4CAF50; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center;}
        .machine { background-color: #F44336; color: white; border-radius: 50%; width: 60px; height: 60px; display:flex; align-items:center; justify-content:center;}
        .selected { border: 4px solid #FFD700; box-shadow: 0 0 10px #FFD700; }
        .valid-move { background-color: #e0e0e0; cursor: pointer; }
        .valid-move:hover { background-color: #81C784; }
        .status { margin: 20px; font-size: 1.2em; color: #555; }
        .btn { padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 5px;}
    </style>
</head>
<body>

    <h1>The Blocking Game</h1>
    <div class="status"><?php echo $game['message']; ?></div>

    <div class="board">
        <?php 
        $valid_destinations = [];
        // If a piece is selected, calculate where it can go for highlighting
        if ($game['selected']) {
            $possible_moves = get_valid_moves($game['board'], HUMAN, $game['selected']);
            foreach ($possible_moves as $m) {
                $valid_destinations[$m['to'][0] . '_' . $m['to'][1]] = true;
            }
        }

        for ($r = 0; $r < BOARD_SIZE; $r++): 
            for ($c = 0; $c < BOARD_SIZE; $c++): 
                $cell = $game['board'][$r][$c];
                $is_selected = ($game['selected'] && $game['selected'][0] == $r && $game['selected'][1] == $c);
                $is_valid_dest = isset($valid_destinations[$r.'_'.$c]);
                
                // Determine CSS classes
                $classes = "cell";
                if ($is_valid_dest) $classes .= " valid-move";
                
                // Determine Link URL
                $link = null;
                if (!$game['game_over']) {
                    if ($cell === HUMAN) {
                        $link = "?action=select&r=$r&c=$c";
                    } elseif ($is_valid_dest) {
                        $link = "?action=move&r=$r&c=$c";
                    }
                }
                
                // Render Cell
                echo "<div class='$classes'>";
                if ($link) echo "<a href='$link' style='display:block; width:100%; height:100%; display:flex; align-items:center; justify-content:center; text-decoration:none;'>";
                
                if ($cell === HUMAN) {
                    echo "<div class='human " . ($is_selected ? 'selected' : '') . "'>You</div>";
                } elseif ($cell === MACHINE) {
                    echo "<div class='machine'>AI</div>";
                } elseif ($is_valid_dest) {
                    echo "<div style='width:20px; height:20px; background:#81C784; border-radius:50%;'></div>";
                }
                
                if ($link) echo "</a>";
                echo "</div>";
            endfor; 
        endfor; 
        ?>
    </div>

    <br>
    <a href="?reset=1" class="btn">Restart Game</a>

</body>
</html>