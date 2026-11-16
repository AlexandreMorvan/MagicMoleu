/*
 * Disposition des joueurs autour d'un téléphone posé à plat au milieu de la table, chacun lisant sa tuile
 * dans son sens. Logique pure, sans DOM.
 */
/**
 * Places autour de la table, dans l'ordre du jeu en partant du coin inférieur gauche.
 *
 * @return {{columns: number, rows: number, places: {row: number, column: number, span: number, rotation: number}[]}}
 */
export function seatLayout(count, portrait, facing = true) {
    const turn = (degrees) => (facing ? degrees : 0);
    if (portrait && count >= 3) {
        const sideRows = Math.floor(count / 2);
        const top = count % 2 === 1;
        const rows = sideRows + (top ? 1 : 0);
        const offset = top ? 1 : 0;
        const places = [{ row: rows, column: 1, span: 1, rotation: turn(90) }];
        for (let r = sideRows; r >= 1; r--) {
            places.push({ row: r + offset, column: 2, span: 1, rotation: turn(270) });
        }
        if (top) {
            places.push({ row: 1, column: 1, span: 2, rotation: turn(180) });
        }
        for (let r = 1; r < sideRows; r++) {
            places.push({ row: r + offset, column: 1, span: 1, rotation: turn(90) });
        }

        return { columns: 2, rows, places };
    }

    // Paysage (et portrait à 2 joueurs) : rangée du bas de gauche à droite, rangée du haut retournée
    const bottom = Math.ceil(count / 2);
    const topCount = count - bottom;
    const places = [];
    for (let i = 0; i < bottom; i++) {
        places.push({ row: topCount ? 2 : 1, column: 1 + i * (12 / bottom), span: 12 / bottom, rotation: 0 });
    }
    for (let i = topCount - 1; i >= 0; i--) {
        places.push({ row: 1, column: 1 + i * (12 / topCount), span: 12 / topCount, rotation: turn(180) });
    }

    return { columns: 12, rows: topCount ? 2 : 1, places };
}
