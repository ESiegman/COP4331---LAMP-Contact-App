-- Seeds the required default admin account. Safe to re-run.
-- Login: root   Password: ChangeMe123!
-- Change this password immediately after first login.
-- Hash generated with PHP: password_hash('ChangeMe123!', PASSWORD_DEFAULT)

INSERT INTO Users (First_Name, Last_Name, Login, Password, Role, Active)
SELECT 'Application', 'Administrator', 'root',
       '$2b$10$NsXc1qxxmUasmvi9NVuuXuCaCdouU664bRMI3i2Jv5drz2hDcgRve',
       'Admin', 1
WHERE NOT EXISTS (SELECT 1 FROM Users WHERE Login = 'root');
