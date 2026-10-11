import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { BookingProvider } from './context/BookingContext';
import { MainLayout } from './components/layout/MainLayout';
import { HomePage } from './pages/HomePage';
import { RoomsPage } from './pages/RoomsPage';
import { ExperiencesPage } from './pages/ExperiencesPage';

function App() {
  return (
    <BookingProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<MainLayout />}>
            <Route index element={<HomePage />} />
            <Route path="rooms" element={<RoomsPage />} />
            <Route path="experiences" element={<ExperiencesPage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </BrowserRouter>
    </BookingProvider>
  );
}

export default App;

