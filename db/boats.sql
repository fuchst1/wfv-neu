USE woerdener_fischereiverein;

INSERT INTO boote (lizenznehmer_id, bootnummer, notizen) VALUES
    (NULL, 'W-28141', 'Reserviert für Neuwerber'),
    (NULL, 'N-26510', NULL),
    (NULL, 'N-30693', 'Beispielnotiz')
ON DUPLICATE KEY UPDATE
    lizenznehmer_id = VALUES(lizenznehmer_id),
    bootnummer = VALUES(bootnummer),
    notizen = VALUES(notizen);
