import React from 'react';
import { HeroSection } from '../components/home/HeroSection';
import { UpcomingTripBanner } from '../components/home/UpcomingTripBanner';
import { RoomRecommendationSection } from '../components/home/RoomRecommendationSection';
import { ExtraServicesSection } from '../components/home/ExtraServicesSection';

export const HomePage = () => {
  return (
    <div className="space-y-4">
      {/* Hero with Search Bar */}
      <HeroSection />

      {/* Upcoming Trip Banner */}
      <UpcomingTripBanner />

      {/* Room Recommendations */}
      <RoomRecommendationSection />

      {/* Extra Services Section */}
      <ExtraServicesSection />
    </div>
  );
};

