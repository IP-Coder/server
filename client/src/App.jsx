import { useState } from "react";
import MarketsList from "./components/MarketsList";

function App() {
    const [selected, setSelected] = useState(null);

    return (
        <div>
            <h1>Markets</h1>
            <MarketsList onSelect={setSelected} />
            {selected && <p>Selected market: {selected}</p>}
        </div>
    );
}

export default App;
