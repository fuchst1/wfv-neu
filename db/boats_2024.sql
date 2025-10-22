USE woerdener_fischereiverein;

INSERT INTO boote_2024 (lizenz_id, bootnummer, notizen) VALUES
    (100, NULL, 'grünes Ruderb.'),
    (75, 'W-28141', NULL),
    (101, 'N-26510', NULL),
    (31, 'W-29004', NULL),
    (81, 'W-28324', NULL),
    (8, 'N-34498', NULL),
    (65, 'N-34804', 'Donauhexe'),
    (33, 'N-35647', NULL),
    (102, 'N-34638', NULL),
    (130, 'W-28755', 'Pilaz Günter'),
    (57, '?????', NULL),
    (98, NULL, 'kein Boot'),
    (88, NULL, 'kein Boot'),
    (93, NULL, 'kein Boot'),
    (79, 'N-30693', NULL),
    (97, 'N-30693', NULL),
    (145, NULL, 'Kajak Tarn'),
    (146, 'N-31378', NULL),
    (84, NULL, 'kein Boot')
ON DUPLICATE KEY UPDATE
    bootnummer = VALUES(bootnummer),
    notizen = VALUES(notizen);
