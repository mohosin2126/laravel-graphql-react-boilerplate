import React from 'react';
import ReactDOM from "react-dom";
import Navbar from './components/navbar';




function App() {
    return (
        <div>
 <Navbar></Navbar>
        </div>
    );
}

ReactDOM.createRoot(document.getElementById('root')).render(
<App/>
);
export default App;
