CREATE TABLE Team (
    team_ID INT PRIMARY KEY,
    team_Name VARCHAR(50) NOT NULL UNIQUE,
    home_arena VARCHAR(50)
)CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Player_Position (
    position_ID INT PRIMARY KEY,
    position_Name VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE User (
    user_ID INT PRIMARY KEY,
    user_PW VARCHAR(255) NOT NULL,
    team_ID INT,
    FOREIGN KEY (team_ID) REFERENCES Team(team_ID)
);

CREATE TABLE Game (
    game_ID INT PRIMARY KEY,
    game_Date DATE NOT NULL,
    round_ID INT NOT NULL,
    home_team_ID INT NOT NULL,
    away_team_ID INT NOT NULL,
    FOREIGN KEY (home_team_ID) REFERENCES Team(team_ID),
    FOREIGN KEY (away_team_ID) REFERENCES Team(team_ID)
);

CREATE TABLE Player (
    player_ID INT PRIMARY KEY,
    player_name VARCHAR(50) NOT NULL,
    current_team_ID INT NOT NULL,
    position_ID INT NOT NULL,
    salary DECIMAL(12, 0),
    FOREIGN KEY (current_team_ID) REFERENCES Team(team_ID),
    FOREIGN KEY (position_ID) REFERENCES Player_Position(position_ID)
)CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE Att_Stats (
    player_ID INT NOT NULL,
    game_ID INT NOT NULL,
    open_att INT DEFAULT 0,
    open_suc INT DEFAULT 0,
    open_failmis INT DEFAULT 0,
    backquick_att INT DEFAULT 0,
    backquick_suc INT DEFAULT 0,
    backquick_failmis INT DEFAULT 0,
    serve_att INT DEFAULT 0,
    serve_suc INT DEFAULT 0,
    serve_mis INT DEFAULT 0,
    PRIMARY KEY (player_ID, game_ID),
    FOREIGN KEY (player_ID) REFERENCES Player(player_ID),
    FOREIGN KEY (game_ID) REFERENCES Game(game_ID)
);

CREATE TABLE L_Stats (
    player_ID INT NOT NULL,
    game_ID INT NOT NULL,
    dig_att INT DEFAULT 0,
    dig_suc INT DEFAULT 0,
    dig_failmis INT DEFAULT 0,
    set_att INT DEFAULT 0,
    set_suc INT DEFAULT 0,
    set_mis INT DEFAULT 0,
    receive_att INT DEFAULT 0,
    receive_good INT DEFAULT 0,
    receive_fail INT DEFAULT 0,
    PRIMARY KEY (player_ID, game_ID),
    FOREIGN KEY (player_ID) REFERENCES Player(player_ID),
    FOREIGN KEY (game_ID) REFERENCES Game(game_ID)
);

CREATE TABLE S_Stats (
    player_ID INT NOT NULL,
    game_ID INT NOT NULL,
    serve_att INT DEFAULT 0,
    serve_suc INT DEFAULT 0,
    serve_mis INT DEFAULT 0,
    set_att INT DEFAULT 0,
    set_suc INT DEFAULT 0,
    set_mis INT DEFAULT 0,
    PRIMARY KEY (player_ID, game_ID),
    FOREIGN KEY (player_ID) REFERENCES Player(player_ID),
    FOREIGN KEY (game_ID) REFERENCES Game(game_ID)
);

CREATE TABLE Scouting_Report (
    report_ID INT PRIMARY KEY AUTO_INCREMENT,
    user_ID INT NOT NULL,
    team_ID INT NOT NULL,
    player_ID INT NOT NULL,
    note_date TIMESTAMP NOT NULL,
    note_content TEXT NOT NULL,
    CONSTRAINT fk_report_user FOREIGN KEY (user_ID) REFERENCES `User`(user_ID),
    CONSTRAINT fk_report_team FOREIGN KEY (team_ID) REFERENCES Team(team_ID),
    CONSTRAINT fk_report_player FOREIGN KEY (player_ID) REFERENCES Player(player_ID)
)CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE INDEX idx_player_position ON Player(position_ID);
