CREATE TABLE IF NOT EXISTS finance_expenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    evidence_file VARCHAR(500) DEFAULT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_finance_expense_date (expense_date),
    KEY idx_finance_expense_status (status),
    CONSTRAINT chk_finance_expense_amount CHECK (amount > 0),
    CONSTRAINT fk_finance_expense_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
