import { useEffect, useState } from "react";
import api from "../services/api";
import Echo from "../services/echo";

const CHUNK_SIZE = 10;

const chunkArray = (arr, size) =>
    Array.from({ length: Math.ceil(arr.length / size) }, (_, i) =>
        arr.slice(i * size, i * size + size)
    );

export default function MarketsList({ onSelect }) {
    const [markets, setMarkets] = useState([]);
    const [symbolChunks, setSymbolChunks] = useState([]);
    const [activeTab, setActiveTab] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const updateMarket = (tick) => {
        setMarkets((prev) =>
            prev.map((m) =>
                m.symbol === tick.symbol
                    ? { ...m, bid: tick.bid, ask: tick.ask }
                    : m
            )
        );
    };

    useEffect(() => {
        api.get("/symbols")
            .then((res) => {
                setMarkets(res.data.symbols);
                setSymbolChunks(chunkArray(res.data.symbols, CHUNK_SIZE));
            })
            .catch((err) => setError(err.message))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        const currentSymbols = symbolChunks[activeTab] || [];
        const activeChannels = [];

        currentSymbols.forEach((m) => {
            const channel = `market.${m.symbol.replace(/[:!]/g, "_")}`;
            Echo.channel(channel).listen("MarketTick", (tick) => {
                updateMarket(tick);
            });
            activeChannels.push(channel);
        });

        return () => {
            activeChannels.forEach((channel) => Echo.leave(channel));
        };
    }, [activeTab, symbolChunks]);

    if (loading) return <p>Loading markets…</p>;
    if (error) return <p>Error: {error}</p>;

    const currentMarkets = symbolChunks[activeTab] || [];

    return (
        <div>
            <div style={{ marginBottom: "1rem" }}>
                {symbolChunks.map((_, idx) => (
                    <button
                        key={idx}
                        onClick={() => setActiveTab(idx)}
                        style={{
                            marginRight: "8px",
                            fontWeight: activeTab === idx ? "bold" : "normal",
                        }}
                    >
                        Tab {idx + 1}
                    </button>
                ))}
            </div>

            <ul>
                {currentMarkets.map((m) => (
                    <li key={m.symbol}>
                        <button onClick={() => onSelect(m.symbol)}>
                            {m.symbol} — {m.bid ?? "-"} / {m.ask ?? "-"}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
